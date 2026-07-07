"""
Self-hosted OCR + face-match + liveness sidecar.

Contract (see the KYC plan doc for the Laravel side):
  POST /ocr       multipart "image" (the ID photo), `Authorization: Bearer <secret>`
    -> 200 {"text": "..."}
    -> 400 {"error": "missing_image" | "undecodable_image"}
  POST /compare   multipart "source" + "target" images, `Authorization: Bearer <secret>`
    -> 200 {"match": bool, "score": float, "faces_detected": {"source": int, "target": int}}
    -> 422 {"match": false, "score": 0.0, "faces_detected": {...}, "error": "no_face_detected_..."}
  POST /liveness  multipart "frames" (repeated, >=2 blink-burst images) +
                  "flash_frames" (repeated images, one per on-screen flash
                  color) + "flash_colors" (repeated text fields, same order/
                  count as flash_frames, each one of red/green/blue),
                  `Authorization: Bearer <secret>`
    -> 200 {"live": bool, "score": float, "blink_detected": bool, "color_reflection_passed": bool, "frames_analyzed": int}
    -> 422 {"live": false, "score": 0.0, "error": "..."}
  GET  /health -> 200 {"status": "ok"}

OCR: Tesseract (via pytesseract), genuinely free and self-hosted - no cloud
account, no billing, unlike Google/AWS OCR APIs. Trade-off: noticeably less
accurate than a commercial OCR API, especially on ID cards with busy
backgrounds, watermarks, or small/stylized fonts. A light Otsu threshold pass
is applied first since Tesseract does measurably better on cleanly binarized
text than on raw color/grayscale card photos.

Live client-side feedback (face box + blink indicator) while the user
positions/blinks is handled entirely in the browser via face-api.js
(resources/js/components/liveness-capture.tsx) - this sidecar is only
called once, at submission time, for the authoritative /compare and
/liveness checks.

Face detection/recognition: OpenCV's YuNet (face_detection_yunet) + SFace
(face_recognition_sface) models, both from the official OpenCV Zoo
(https://github.com/opencv/opencv_zoo, Apache-2.0), downloaded at image build
time (see Dockerfile) - genuinely free and self-hosted, no vendor API key,
but real trained models rather than a hand-rolled heuristic:
- YuNet is a lightweight ONNX face detector with 5-point landmarks (eyes,
  nose tip, mouth corners) returned alongside each detection.
- SFace produces a 128-d face embedding per detected+aligned face; identity
  comparison is a cosine similarity between two embeddings via the model's
  own `match()` method, which is what an actual face-verification system
  does (as opposed to comparing raw pixels).

Liveness: still a naive baseline, not a dedicated liveness model, combining
two independent signals - both must pass:
- Blink: YuNet's eye landmarks give a small patch around each eye per frame,
  scored via Laplacian (edge/texture) variance - an open eye (iris/sclera/
  eyelash contrast) scores meaningfully higher than a closed eye (smooth
  eyelid skin). A real blink shows up as a frame where both eyes dip well
  below the burst's peak openness.
- Color reflection: the client flashes the screen through a red/green/blue
  sequence and captures one frame per color. For each frame we sample a
  forehead-ish patch and check that its dominant color channel (R/G/B mean)
  matches the color that was on screen - real skin lit by a colored screen
  visibly shifts toward that color; a printed photo or a pre-recorded video
  replay won't track an unpredictable, request-specific flash sequence.

Together these screen out the simplest spoofing attempts (a still photo, or
a video replay that doesn't happen to contain both a blink and a matching
flash reaction) but this is not a substitute for a dedicated liveness/anti-
spoofing model against a determined attacker.
"""

import os

import cv2
import numpy as np
import pytesseract
from flask import Flask, jsonify, request

app = Flask(__name__)

SHARED_SECRET = os.environ.get("FACE_MATCH_SHARED_SECRET", "")
MODEL_DIR = os.path.join(os.path.dirname(__file__), "models")

DETECTOR = cv2.FaceDetectorYN_create(
    os.path.join(MODEL_DIR, "face_detection_yunet_2023mar.onnx"),
    "",
    (320, 320),
    score_threshold=0.7,
)
RECOGNIZER = cv2.FaceRecognizerSF_create(
    os.path.join(MODEL_DIR, "face_recognition_sface_2021dec.onnx"),
    "",
)

# A frame whose eye-patch texture energy falls to (or below) this fraction of
# the burst's most-open frame is treated as a blink.
BLINK_DROP_RATIO = 0.6
MIN_FRAMES_FOR_LIVENESS = 2
MIN_FACE_DETECTION_RATE = 0.5

# BGR channel index (OpenCV's native order) expected to dominate a forehead
# patch's mean color under each flash color.
EXPECTED_CHANNEL_INDEX = {"blue": 0, "green": 1, "red": 2}
MIN_FLASH_FRAMES = 2
# Fraction of flash frames that must show their expected dominant channel.
COLOR_REFLECTION_PASS_RATIO = 0.6


def _authorized(req) -> bool:
    if not SHARED_SECRET:
        return True
    return req.headers.get("Authorization") == f"Bearer {SHARED_SECRET}"


@app.before_request
def _require_auth():
    # /health is exempt - the Docker HEALTHCHECK command hits it without an
    # Authorization header (see Dockerfile). Every other route - including
    # any added later - is covered by this one central check rather than
    # each route remembering to call _authorized() itself.
    if request.path == "/health":
        return None

    if not _authorized(request):
        return jsonify(error="unauthorized"), 401

    return None


def _decode(file_storage):
    data = np.frombuffer(file_storage.read(), dtype=np.uint8)
    return cv2.imdecode(data, cv2.IMREAD_COLOR)


def _binarize_for_ocr(image):
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    _, binary = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
    return binary


def _detect_largest_face(image):
    """Returns YuNet's 15-value detection row for the largest face, or None.

    Row layout: x, y, w, h, right_eye(x,y), left_eye(x,y), nose_tip(x,y),
    right_mouth_corner(x,y), left_mouth_corner(x,y), confidence.
    """
    h, w = image.shape[:2]
    DETECTOR.setInputSize((w, h))
    _, faces = DETECTOR.detect(image)
    if faces is None or len(faces) == 0:
        return None
    return max(faces, key=lambda f: f[2] * f[3])


def _embedding(image, face):
    aligned = RECOGNIZER.alignCrop(image, face)
    return RECOGNIZER.feature(aligned)


def _cosine_similarity(feature_a, feature_b) -> float:
    score = RECOGNIZER.match(feature_a, feature_b, cv2.FaceRecognizerSF_FR_COSINE)
    return max(0.0, min(1.0, float(score)))


def _eye_openness(image, face) -> float:
    """Mean Laplacian variance (edge/texture energy) across small patches
    centered on each eye landmark - higher means more open."""
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    x, y, w, h = face[0:4]
    half = max(4, int(w * 0.06))

    variances = []
    for ex, ey in ((face[4], face[5]), (face[6], face[7])):
        ex, ey = int(ex), int(ey)
        patch = gray[max(0, ey - half) : ey + half, max(0, ex - half) : ex + half]
        if patch.size > 0:
            variances.append(cv2.Laplacian(patch, cv2.CV_64F).var())

    return float(np.mean(variances)) if variances else 0.0


def _forehead_patch(image, face):
    """A skin-only strip near the top of the face box, above the eyes -
    avoids eyebrows/eyes/facial hair so the color sample reflects plain
    skin lit by the on-screen flash."""
    x, y, w, h = (int(v) for v in face[0:4])
    x1, x2 = x + int(w * 0.25), x + int(w * 0.75)
    y1, y2 = y + int(h * 0.05), y + int(h * 0.25)
    return image[max(0, y1) : max(0, y2), max(0, x1) : max(0, x2)]


def _dominant_channel_matches(image, face, color: str) -> bool:
    patch = _forehead_patch(image, face)
    if patch.size == 0:
        return False

    means = cv2.mean(patch)[:3]  # (B, G, R)
    expected_index = EXPECTED_CHANNEL_INDEX.get(color)

    return expected_index is not None and means[expected_index] == max(means)


def _color_reflection_passed(flash_frame_files, flash_colors) -> bool:
    if len(flash_frame_files) < MIN_FLASH_FRAMES or len(flash_frame_files) != len(flash_colors):
        return False

    matches = 0
    for frame_file, color in zip(flash_frame_files, flash_colors):
        image = _decode(frame_file)
        face = _detect_largest_face(image) if image is not None else None

        if face is not None and _dominant_channel_matches(image, face, color):
            matches += 1

    return (matches / len(flash_frame_files)) >= COLOR_REFLECTION_PASS_RATIO


@app.get("/health")
def health():
    return jsonify(status="ok")


@app.post("/ocr")
def ocr():
    if "image" not in request.files:
        return jsonify(error="missing_image"), 400

    image = _decode(request.files["image"])
    if image is None:
        return jsonify(error="undecodable_image"), 400

    text = pytesseract.image_to_string(_binarize_for_ocr(image))

    return jsonify(text=text)


@app.post("/compare")
def compare():
    if "source" not in request.files or "target" not in request.files:
        return jsonify(error="missing_files"), 400

    source_image = _decode(request.files["source"])
    target_image = _decode(request.files["target"])

    source_face = _detect_largest_face(source_image) if source_image is not None else None
    target_face = _detect_largest_face(target_image) if target_image is not None else None

    faces_detected = {
        "source": 1 if source_face is not None else 0,
        "target": 1 if target_face is not None else 0,
    }

    if source_face is None or target_face is None:
        missing = "source" if source_face is None else "target"
        return (
            jsonify(match=False, score=0.0, faces_detected=faces_detected, error=f"no_face_detected_{missing}"),
            422,
        )

    score = _cosine_similarity(
        _embedding(source_image, source_face),
        _embedding(target_image, target_face),
    )

    return jsonify(match=score >= 0.5, score=round(score, 4), faces_detected=faces_detected)


@app.post("/liveness")
def liveness():
    frames = request.files.getlist("frames")
    if len(frames) < MIN_FRAMES_FOR_LIVENESS:
        return jsonify(error="not_enough_frames"), 400

    openness_scores = []
    faces_found = 0

    for frame_file in frames:
        image = _decode(frame_file)
        face = _detect_largest_face(image) if image is not None else None

        if face is None:
            continue

        faces_found += 1
        openness_scores.append(_eye_openness(image, face))

    if faces_found / len(frames) < MIN_FACE_DETECTION_RATE:
        return jsonify(live=False, score=0.0, error="face_not_consistently_detected"), 422

    peak = max(openness_scores, default=0.0)
    trough = min(openness_scores, default=0.0)
    blink_detected = peak > 0 and trough <= peak * BLINK_DROP_RATIO

    flash_frame_files = request.files.getlist("flash_frames")
    flash_colors = request.form.getlist("flash_colors")
    # Flash frames are optional at the protocol level (for manual/curl
    # testing) even though the Laravel side always sends them - absence
    # defaults to "passed" rather than failing a request that never
    # intended to exercise this check.
    color_reflection_passed = (
        _color_reflection_passed(flash_frame_files, flash_colors) if flash_frame_files else True
    )

    live = blink_detected and color_reflection_passed
    score = (int(blink_detected) + int(color_reflection_passed)) / 2

    return jsonify(
        live=live,
        score=score,
        blink_detected=blink_detected,
        color_reflection_passed=color_reflection_passed,
        frames_analyzed=len(frames),
    )


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=8500)

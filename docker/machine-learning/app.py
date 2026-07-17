"""Self-hosted OCR + face-match + liveness sidecar - composition root.

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

Route logic lives in ocr.py (Tesseract-only) and face_detection.py
(OpenCV YuNet/SFace-only) as separate Flask Blueprints - this file only
wires them together and hosts what's genuinely shared: the health check and
the auth gate, neither of which is OCR- or face-specific.

Live client-side feedback (face box + blink indicator) while the user
positions/blinks is handled entirely in the browser via face-api.js
(resources/js/components/liveness-capture.tsx) - this sidecar is only
called once, at submission time, for the authoritative /compare and
/liveness checks.
"""

import os

from flask import Flask, jsonify, request

from face_detection import face_bp
from ocr import ocr_bp

app = Flask(__name__)
app.register_blueprint(ocr_bp)
app.register_blueprint(face_bp)

SHARED_SECRET = os.environ.get("MACHINE_LEARNING_SHARED_SECRET", "")


def _authorized(req) -> bool:
    if not SHARED_SECRET:
        return True
    return req.headers.get("Authorization") == f"Bearer {SHARED_SECRET}"


@app.before_request
def _require_auth():
    # /health is exempt - the Docker HEALTHCHECK command hits it without an
    # Authorization header (see Dockerfile). Every other route - including
    # any added later, in either blueprint - is covered by this one central
    # check rather than each route remembering to call _authorized() itself.
    if request.path == "/health":
        return None

    if not _authorized(request):
        return jsonify(error="unauthorized"), 401

    return None


@app.get("/health")
def health():
    return jsonify(status="ok")


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=8500)

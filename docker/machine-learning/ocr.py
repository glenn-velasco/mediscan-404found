"""OCR blueprint - Tesseract only, no OpenCV/face models here.

OCR: Tesseract (via pytesseract), genuinely free and self-hosted - no cloud
account, no billing, unlike Google/AWS OCR APIs. Trade-off: noticeably less
accurate than a commercial OCR API, especially on ID cards with busy
backgrounds, watermarks, or small/stylized fonts. A light Otsu threshold pass
is applied first since Tesseract does measurably better on cleanly binarized
text than on raw color/grayscale card photos.
"""

import cv2
import pytesseract
from flask import Blueprint, jsonify, request

from imaging import decode, demo_enabled

ocr_bp = Blueprint("ocr", __name__)

# Canned OCR text returned in demo mode - simulates a readable PRC ID so the
# full verification pipeline can be exercised without a real ID photo.
DEMO_OCR_TEXT = (
    "Republic of the Philippines\n"
    "Professional Regulation Commission\n"
    "Professional Identification Card\n"
    "LAST NAME\tDELA CRUZ\n"
    "FIRST NAME\tJUAN\n"
    "MIDDLE NAME\tSANTOS\n"
    "REGISTRATION NO.\t0012345\n"
    "REGISTRATION DATE\t04/05/2019\n"
    "VALID UNTIL\t11/25/2022\n"
    "NURSE"
)


def _prepare_for_ocr(image):
    """Enhance the image for better Tesseract recognition on ID cards with
    busy backgrounds, watermarks, or uneven lighting.

    Generates three candidate preprocessed images, runs Tesseract's word-
    confidence scoring on each, and returns the winner:

    1. Bilateral filter + Otsu — best for clean, well-lit cards
    2. CLAHE + adaptive threshold — best for uneven lighting / low contrast
    3. Denoised grayscale (no binarization) — lets Tesseract threshold
       internally, avoiding watermark/texture strokes being misread as text
    """
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)

    # Upscale small images so Tesseract gets more pixels per glyph.
    # ID card label text is small; character segmentation improves
    # substantially when the shorter side has at least ~1000 px.
    h, w = gray.shape[:2]
    if min(h, w) < 1000:
        scale = 1000 / min(h, w)
        gray = cv2.resize(gray, None, fx=scale, fy=scale,
                          interpolation=cv2.INTER_CUBIC)

    # --- Candidate 1: Bilateral filter + Otsu ---
    denoised = cv2.bilateralFilter(gray, 9, 75, 75)
    _, otsu = cv2.threshold(denoised, 0, 255,
                            cv2.THRESH_BINARY + cv2.THRESH_OTSU)

    # --- Candidate 2: CLAHE + adaptive threshold ---
    clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
    enhanced = clahe.apply(gray)
    blurred = cv2.GaussianBlur(enhanced, (0, 0), 3)
    sharpened = cv2.addWeighted(enhanced, 1.5, blurred, -0.5, 0)
    adaptive = cv2.adaptiveThreshold(
        sharpened, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
        cv2.THRESH_BINARY, 31, 2,
    )

    # --- Candidate 3: Denoised grayscale (no binarization) ---
    # Aggressive adaptive thresholding can pick up watermark strokes as
    # text strokes.  Letting Tesseract's internal Otsu handle the
    # binarization on clean grayscale avoids that corruption.
    denoised_gray = cv2.bilateralFilter(gray, 9, 75, 75)

    candidates = [otsu, adaptive, denoised_gray]

    # --- Pick the candidate with highest Tesseract word confidence ---
    from pytesseract import Output

    best = candidates[0]
    best_conf = -1.0

    for candidate in candidates:
        data = pytesseract.image_to_data(
            candidate, output_type=Output.DICT,
            config="--psm 6 --oem 3",
        )
        # conf entries are -1 for non-text blocks; skip those.
        confs = [int(c) for c in data["conf"] if isinstance(c, (int, float)) and c > 0]
        if confs:
            mean_conf = sum(confs) / len(confs)
            if mean_conf > best_conf:
                best_conf = mean_conf
                best = candidate

    # Light morphological opening on binary results only to remove
    # small noise dots without eroding text strokes.  Skip for the
    # grayscale candidate — the op would blur rather than clean it.
    if len(best.shape) == 2 and best.max() == 255 and best.min() == 0:
        kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (2, 2))
        best = cv2.morphologyEx(best, cv2.MORPH_OPEN, kernel)

    return best


@ocr_bp.post("/ocr")
def ocr():
    if "image" not in request.files:
        return jsonify(error="missing_image"), 400

    if demo_enabled():
        return jsonify(text=DEMO_OCR_TEXT)

    image = decode(request.files["image"])
    if image is None:
        return jsonify(error="undecodable_image"), 400

    prepared = _prepare_for_ocr(image)

    # PSM 6 (single uniform block) tends to work best for ID cards.
    text = pytesseract.image_to_string(
        prepared,
        config="--psm 6 --oem 3",
    )

    return jsonify(text=text)

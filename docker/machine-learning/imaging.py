"""Shared helpers used by both the OCR and face-detection blueprints -
image decoding and demo-mode detection. Kept separate rather than duplicated
into ocr.py/face_detection.py since both need the exact same behavior."""

import os

import cv2
import numpy as np
from flask import request

DEMO_MODE = os.environ.get("DEMO_MODE", "").lower() in ("1", "true", "yes")


def demo_enabled() -> bool:
    """Return True when the request opts into demo mode via the DEMO_MODE env
    var or the ``?demo=true`` query parameter."""
    return DEMO_MODE or request.args.get("demo", "").lower() in ("1", "true", "yes")


def decode(file_storage):
    data = np.frombuffer(file_storage.read(), dtype=np.uint8)
    return cv2.imdecode(data, cv2.IMREAD_COLOR)

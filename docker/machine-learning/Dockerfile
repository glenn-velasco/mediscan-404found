FROM python:3.12-slim

WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends curl tesseract-ocr \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Pretrained models from the official OpenCV Zoo (Apache-2.0), fetched at
# build time - no vendor account/API key, genuinely free forever.
# https://github.com/opencv/opencv_zoo
RUN mkdir -p models \
    && curl --retry 5 --retry-delay 3 --retry-all-errors -fL -o models/face_detection_yunet_2023mar.onnx \
        https://github.com/opencv/opencv_zoo/raw/main/models/face_detection_yunet/face_detection_yunet_2023mar.onnx \
    && curl --retry 5 --retry-delay 3 --retry-all-errors -fL -o models/face_recognition_sface_2021dec.onnx \
        https://github.com/opencv/opencv_zoo/raw/main/models/face_recognition_sface/face_recognition_sface_2021dec.onnx

COPY app.py ocr.py face_detection.py imaging.py ./

EXPOSE 8500

HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD python -c "import urllib.request; urllib.request.urlopen('http://127.0.0.1:8500/health')" || exit 1

CMD ["gunicorn", "--bind", "0.0.0.0:8500", "--workers", "2", "--worker-class", "gthread", "--threads", "4", "--timeout", "30", "app:app"]

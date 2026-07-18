# Load Test Fixtures

This directory contains sample files used in load tests, particularly for the professional-applications upload scenario.

## Required Files

- `sample-id.jpg` — Sample government ID image (JPEG, ~100KB)
- `sample-selfie.jpg` — Sample biometric selfie (JPEG, ~100KB)

## Generating Sample Fixtures

You can create dummy image files for testing:

```bash
# Generate a 100KB dummy JPEG
dd if=/dev/zero bs=1024 count=100 | ffmpeg -f rawvideo -pixel_format rgb24 -video_size 100x100 -framerate 1 -i - -c:v libx264 -pix_fmt yuv420p sample-id.jpg

# Or use ImageMagick if available
convert -size 100x100 xc:blue sample-id.jpg
convert -size 100x100 xc:green sample-selfie.jpg
```

Or just use any actual image files (JPEGs or PNGs) as stand-ins.

## Usage in Load Tests

The browser-based load test (`loadtest/k6/browser.js`) doesn't currently use fixtures, but the API scenario can be extended to use them for professional-applications uploads:

```javascript
// Future enhancement: multipart upload with fixtures
let file = open('./fixtures/sample-id.jpg', 'b');
let body = http.file(file, 'id.jpg', 'image/jpeg');
```

See k6 docs on [Multipart Requests](https://k6.io/docs/examples/multipart-requests/) for more details.

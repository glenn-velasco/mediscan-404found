<?php

namespace App\Services\Kyc;

use App\Contracts\Kyc\FaceMatchClientContract;
use App\Contracts\Kyc\OcrClientContract;
use App\Exceptions\KycSidecarUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HttpKycSidecarClient implements FaceMatchClientContract, OcrClientContract
{
    public function detectText(string $disk, string $path): string
    {
        $response = $this->post(
            '/ocr',
            fn (PendingRequest $request) => $request->attach('image', Storage::disk($disk)->get($path), 'image.jpg')
        );

        $response->throw();

        return (string) $response->json('text');
    }

    /**
     * @return array{match: bool, score: float, faces_detected: array{source: int, target: int}}
     */
    public function compare(string $disk, string $sourcePath, string $targetPath): array
    {
        $response = $this->post(
            '/compare',
            fn (PendingRequest $request) => $request
                ->attach('source', Storage::disk($disk)->get($sourcePath), 'source.jpg')
                ->attach('target', Storage::disk($disk)->get($targetPath), 'target.jpg')
        );

        if ($response->status() === 422) {
            return [
                'match' => false,
                'score' => 0.0,
                'faces_detected' => $response->json('faces_detected', ['source' => 0, 'target' => 0]),
            ];
        }

        $response->throw();

        return [
            'match' => (bool) $response->json('match'),
            'score' => (float) $response->json('score'),
            'faces_detected' => $response->json('faces_detected', ['source' => 1, 'target' => 1]),
        ];
    }

    /**
     * @param  array<int, string>  $framePaths
     * @param  array<int, array{path: string, color: string}>  $flashFrames
     * @return array{live: bool, score: float, blink_detected: bool, color_reflection_passed: bool}
     */
    public function checkLiveness(string $disk, array $framePaths, array $flashFrames): array
    {
        $response = $this->post('/liveness', function (PendingRequest $request) use ($disk, $framePaths, $flashFrames) {
            foreach ($framePaths as $index => $path) {
                $request->attach('frames', Storage::disk($disk)->get($path), "frame-{$index}.jpg");
            }

            foreach ($flashFrames as $index => $flashFrame) {
                $request->attach('flash_frames', Storage::disk($disk)->get($flashFrame['path']), "flash-{$index}.jpg");
                $request->attach('flash_colors', $flashFrame['color']);
            }

            return $request;
        });

        if ($response->status() === 422) {
            return ['live' => false, 'score' => 0.0, 'blink_detected' => false, 'color_reflection_passed' => false];
        }

        $response->throw();

        return [
            'live' => (bool) $response->json('live'),
            'score' => (float) $response->json('score'),
            'blink_detected' => (bool) $response->json('blink_detected'),
            'color_reflection_passed' => (bool) $response->json('color_reflection_passed'),
        ];
    }

    /**
     * @param  callable(PendingRequest): PendingRequest  $withPayload
     *
     * @throws KycSidecarUnavailableException when the sidecar cannot be reached after retries
     */
    private function post(string $path, callable $withPayload): Response
    {
        try {
            // throw: false — a definitive 422 (e.g. "no face detected") or a
            // persistent 5xx must reach our own status handling in the
            // callers above rather than being converted into a
            // RequestException by the retry helper's default behavior. The
            // `when` callback still retries transient server errors, just
            // not deterministic 4xx responses.
            $request = Http::retry(2, 200, when: fn (Throwable $exception) => ! (
                $exception instanceof RequestException && $exception->response->clientError()
            ), throw: false)
                ->timeout((int) config('services.face_match.timeout'))
                ->withToken(config('services.face_match.token'));

            $response = $withPayload($request)->post(config('services.face_match.base_url').$path);
        } catch (ConnectionException $e) {
            throw new KycSidecarUnavailableException(previous: $e);
        }

        if ($response->serverError() || $response->status() === 408) {
            throw new KycSidecarUnavailableException;
        }

        return $response;
    }
}

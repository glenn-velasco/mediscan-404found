<?php

namespace App\Contracts\Kyc;

use App\Exceptions\KycSidecarUnavailableException;

interface FaceMatchClientContract
{
    /**
     * Compare the face in a source image (selfie) against a target image (ID
     * photo), given as raw bytes.
     *
     * @return array{match: bool, score: float, faces_detected: array{source: int, target: int}}
     *
     * @throws KycSidecarUnavailableException when the sidecar cannot be reached after retries
     */
    public function compare(string $sourceContents, string $targetContents): array;

    /**
     * Check a burst of selfie frames for liveness: a detected blink and a
     * face whose lighting reacts to an on-screen RGB flash challenge, to
     * guard against a printed photo or a replayed video held up to the
     * camera. Naive heuristic - not a substitute for a dedicated liveness
     * model, see docker/face-match/app.py.
     *
     * @param  array<int, string>  $frameContents  blink-detection burst, raw bytes
     * @param  array<int, array{contents: string, color: string}>  $flashFrames  one frame per on-screen flash color, raw bytes
     * @param  bool  $clientBlinkDetected  when true, the server trusts the client's blink detection (e.g. ML Kit) and skips its own
     * @return array{live: bool, score: float, blink_detected: bool, color_reflection_passed: bool}
     *
     * @throws KycSidecarUnavailableException when the sidecar cannot be reached after retries
     */
    public function checkLiveness(array $frameContents, array $flashFrames, bool $clientBlinkDetected = false): array;
}

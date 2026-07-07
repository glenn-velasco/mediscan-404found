<?php

namespace App\Contracts\Kyc;

use App\Exceptions\KycSidecarUnavailableException;

interface FaceMatchClientContract
{
    /**
     * Compare the face in a source image (selfie) against a target image (ID
     * photo), both read from the given disk.
     *
     * @return array{match: bool, score: float, faces_detected: array{source: int, target: int}}
     *
     * @throws KycSidecarUnavailableException when the sidecar cannot be reached after retries
     */
    public function compare(string $disk, string $sourcePath, string $targetPath): array;

    /**
     * Check a burst of selfie frames for liveness: a detected blink and a
     * face whose lighting reacts to an on-screen RGB flash challenge, to
     * guard against a printed photo or a replayed video held up to the
     * camera. Naive heuristic - not a substitute for a dedicated liveness
     * model, see docker/face-match/app.py.
     *
     * @param  array<int, string>  $framePaths  blink-detection burst
     * @param  array<int, array{path: string, color: string}>  $flashFrames  one frame per on-screen flash color
     * @return array{live: bool, score: float, blink_detected: bool, color_reflection_passed: bool}
     *
     * @throws KycSidecarUnavailableException when the sidecar cannot be reached after retries
     */
    public function checkLiveness(string $disk, array $framePaths, array $flashFrames): array;
}

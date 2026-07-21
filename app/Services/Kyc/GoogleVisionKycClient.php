<?php

namespace App\Services\Kyc;

use App\Contracts\Kyc\FaceMatchClientContract;
use App\Contracts\Kyc\OcrClientContract;
use App\Exceptions\KycSidecarUnavailableException;
use Google\ApiCore\ApiException;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Cloud\Vision\V1\AnnotateImageRequest;
use Google\Cloud\Vision\V1\AnnotateImageResponse;
use Google\Cloud\Vision\V1\BatchAnnotateImagesRequest;
use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\FaceAnnotation;
use Google\Cloud\Vision\V1\FaceAnnotation\Landmark\Type as LandmarkType;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\Feature\Type as FeatureType;
use Google\Cloud\Vision\V1\Image;
use Google\Cloud\Vision\V1\Likelihood;

/**
 * Google Cloud Vision-backed OCR and face detection.
 *
 * Vision's Face Detection returns per-face landmarks, pose angles, and
 * quality/exposure likelihoods (see FaceAnnotation), but - unlike the
 * SFace-based machine-learning sidecar this replaces as default - it has no
 * purpose-built identity-matching or liveness model. compare() and
 * checkLiveness() below are heuristics derived from that annotation data
 * (landmark geometry for matching; eye-aspect-ratio dip for blink detection
 * and exposure likelihood for color-flash liveness), not a dedicated
 * face-matching/liveness product.
 */
class GoogleVisionKycClient implements FaceMatchClientContract, OcrClientContract
{
    /**
     * Landmark distance beyond which two faces are considered maximally
     * dissimilar (score floors at 0). Calibrated empirically against
     * same-person vs different-person selfie/ID photo pairs.
     */
    private const MATCH_CALIBRATION_CONSTANT = 0.6;

    /**
     * Relative dip in eye-aspect-ratio (EAR) below the burst's peak that
     * counts as a blink. Looser than the frontend's own client-side detector
     * (resources/js/components/liveness-capture.tsx, EAR_DROP_RATIO=0.85) -
     * that one samples continuously and can catch the eye fully shut, while
     * this burst is a handful of discrete frames (measured ~8-9% dip across
     * a real blink in a 7-frame burst) that rarely land on full closure.
     */
    private const EAR_DROP_RATIO = 0.93;

    public function __construct(private readonly ?ImageAnnotatorClient $client = null) {}

    public function detectText(string $imageContents): string
    {
        $response = $this->annotate($imageContents, FeatureType::DOCUMENT_TEXT_DETECTION);

        $fullText = $response->getFullTextAnnotation();

        return $fullText === null ? '' : $fullText->getText();
    }

    /**
     * @return array{match: bool, score: float, faces_detected: array{source: int, target: int}}
     */
    public function compare(string $sourceContents, string $targetContents): array
    {
        $sourceFaces = $this->detectFaces($sourceContents);
        $targetFaces = $this->detectFaces($targetContents);

        $facesDetected = ['source' => count($sourceFaces), 'target' => count($targetFaces)];

        if ($sourceFaces === [] || $targetFaces === []) {
            return ['match' => false, 'score' => 0.0, 'faces_detected' => $facesDetected];
        }

        $score = $this->landmarkSimilarity(
            $this->topFace($sourceFaces),
            $this->topFace($targetFaces),
        );

        return [
            'match' => $score >= (float) config('kyc.face_match_hard_floor'),
            'score' => $score,
            'faces_detected' => $facesDetected,
        ];
    }

    /**
     * @param  array<int, string>  $frameContents
     * @param  array<int, array{contents: string, color: string}>  $flashFrames
     * @return array{live: bool, score: float, blink_detected: bool, color_reflection_passed: bool}
     */
    public function checkLiveness(array $frameContents, array $flashFrames): array
    {
        $burstFaces = array_values(array_filter(array_map(
            fn (string $contents) => $this->topFace($this->detectFaces($contents)),
            $frameContents,
        )));

        if (count($burstFaces) < 2) {
            return ['live' => false, 'score' => 0.0, 'blink_detected' => false, 'color_reflection_passed' => false];
        }

        $blinkDetected = $this->blinkDetected($burstFaces);
        $colorReflectionPassed = $this->exposureVariesAcrossFlashes($flashFrames);

        $score = ($blinkDetected ? 0.5 : 0.0) + ($colorReflectionPassed ? 0.5 : 0.0);

        return [
            'live' => $blinkDetected && $colorReflectionPassed,
            'score' => $score,
            'blink_detected' => $blinkDetected,
            'color_reflection_passed' => $colorReflectionPassed,
        ];
    }

    /**
     * @return array<int, FaceAnnotation>
     */
    private function detectFaces(string $imageContents): array
    {
        $response = $this->annotate($imageContents, FeatureType::FACE_DETECTION);

        return iterator_to_array($response->getFaceAnnotations());
    }

    /**
     * @param  array<int, FaceAnnotation>  $faces
     */
    private function topFace(array $faces): ?FaceAnnotation
    {
        if ($faces === []) {
            return null;
        }

        usort($faces, fn (FaceAnnotation $a, FaceAnnotation $b) => $b->getDetectionConfidence() <=> $a->getDetectionConfidence());

        return $faces[0];
    }

    private function landmarkSimilarity(FaceAnnotation $source, FaceAnnotation $target): float
    {
        $sourceLandmarks = $this->normalizedLandmarks($source);
        $targetLandmarks = $this->normalizedLandmarks($target);

        $sharedTypes = array_intersect_key($sourceLandmarks, $targetLandmarks);

        if ($sharedTypes === []) {
            return 0.0;
        }

        $distances = [];

        foreach (array_keys($sharedTypes) as $type) {
            [$sx, $sy] = $sourceLandmarks[$type];
            [$tx, $ty] = $targetLandmarks[$type];

            $distances[] = sqrt((($sx - $tx) ** 2) + (($sy - $ty) ** 2));
        }

        $averageDistance = array_sum($distances) / count($distances);

        return max(0.0, 1 - min(1.0, $averageDistance / self::MATCH_CALIBRATION_CONSTANT));
    }

    /**
     * Landmark positions re-centered on the midpoint between the eyes and
     * scaled by inter-ocular distance, so pose/scale/translation mostly
     * cancel out before comparing two faces.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    private function normalizedLandmarks(FaceAnnotation $face): array
    {
        $points = [];

        foreach ($face->getLandmarks() as $landmark) {
            $position = $landmark->getPosition();
            $points[$landmark->getType()] = [$position->getX(), $position->getY()];
        }

        if (! isset($points[LandmarkType::LEFT_EYE], $points[LandmarkType::RIGHT_EYE])) {
            return [];
        }

        [$leftEyeX, $leftEyeY] = $points[LandmarkType::LEFT_EYE];
        [$rightEyeX, $rightEyeY] = $points[LandmarkType::RIGHT_EYE];

        $centerX = ($leftEyeX + $rightEyeX) / 2;
        $centerY = ($leftEyeY + $rightEyeY) / 2;
        $interOcularDistance = sqrt((($leftEyeX - $rightEyeX) ** 2) + (($leftEyeY - $rightEyeY) ** 2));

        if ($interOcularDistance <= 0.0) {
            return [];
        }

        $normalized = [];

        foreach ($points as $type => [$x, $y]) {
            $normalized[$type] = [
                ($x - $centerX) / $interOcularDistance,
                ($y - $centerY) / $interOcularDistance,
            ];
        }

        return $normalized;
    }

    /**
     * A blink is a dip in eye-aspect-ratio (how open the eyes are, relative
     * to eye width) below EAR_DROP_RATIO of the burst's peak - the same
     * signal the frontend's own client-side detector uses, just computed
     * from Vision's eye-boundary landmarks instead of face-api.js.
     *
     * @param  array<int, FaceAnnotation>  $faces
     */
    private function blinkDetected(array $faces): bool
    {
        $ratios = array_values(array_filter(array_map(
            fn (FaceAnnotation $face) => $this->eyeAspectRatio($face),
            $faces,
        ), fn (?float $ratio) => $ratio !== null));

        if (count($ratios) < 2) {
            return false;
        }

        return min($ratios) <= max($ratios) * self::EAR_DROP_RATIO;
    }

    /**
     * Average of both eyes' (vertical eyelid gap / eye width) - lower means
     * more closed. Null if the relevant eye-boundary landmarks weren't
     * returned for this face.
     */
    private function eyeAspectRatio(FaceAnnotation $face): ?float
    {
        $points = [];

        foreach ($face->getLandmarks() as $landmark) {
            $position = $landmark->getPosition();
            $points[$landmark->getType()] = [$position->getX(), $position->getY()];
        }

        $left = $this->singleEyeAspectRatio(
            $points,
            LandmarkType::LEFT_EYE_TOP_BOUNDARY,
            LandmarkType::LEFT_EYE_BOTTOM_BOUNDARY,
            LandmarkType::LEFT_EYE_LEFT_CORNER,
            LandmarkType::LEFT_EYE_RIGHT_CORNER,
        );

        $right = $this->singleEyeAspectRatio(
            $points,
            LandmarkType::RIGHT_EYE_TOP_BOUNDARY,
            LandmarkType::RIGHT_EYE_BOTTOM_BOUNDARY,
            LandmarkType::RIGHT_EYE_LEFT_CORNER,
            LandmarkType::RIGHT_EYE_RIGHT_CORNER,
        );

        $available = array_filter([$left, $right], fn (?float $ratio) => $ratio !== null);

        return $available === [] ? null : array_sum($available) / count($available);
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $points
     */
    private function singleEyeAspectRatio(array $points, int $top, int $bottom, int $leftCorner, int $rightCorner): ?float
    {
        if (! isset($points[$top], $points[$bottom], $points[$leftCorner], $points[$rightCorner])) {
            return null;
        }

        [$topX, $topY] = $points[$top];
        [$bottomX, $bottomY] = $points[$bottom];
        [$leftX, $leftY] = $points[$leftCorner];
        [$rightX, $rightY] = $points[$rightCorner];

        $eyeWidth = sqrt((($leftX - $rightX) ** 2) + (($leftY - $rightY) ** 2));

        if ($eyeWidth <= 0.0) {
            return null;
        }

        $eyeGap = sqrt((($topX - $bottomX) ** 2) + (($topY - $bottomY) ** 2));

        return $eyeGap / $eyeWidth;
    }

    /**
     * @param  array<int, array{contents: string, color: string}>  $flashFrames
     */
    private function exposureVariesAcrossFlashes(array $flashFrames): bool
    {
        if (count($flashFrames) < 2) {
            return false;
        }

        $likelihoods = [];

        foreach ($flashFrames as $flashFrame) {
            $face = $this->topFace($this->detectFaces($flashFrame['contents']));

            if ($face === null) {
                return false;
            }

            $likelihoods[] = $face->getUnderExposedLikelihood();
        }

        return count(array_unique($likelihoods)) > 1 || max($likelihoods) <= Likelihood::POSSIBLE;
    }

    private function annotate(string $imageContents, int $featureType): AnnotateImageResponse
    {
        $client = $this->client ?? new ImageAnnotatorClient(['credentials' => $this->credentials()]);

        $request = (new AnnotateImageRequest)
            ->setImage((new Image)->setContent($imageContents))
            ->setFeatures([(new Feature)->setType($featureType)]);

        try {
            $batchResponse = $client->batchAnnotateImages(
                (new BatchAnnotateImagesRequest)->setRequests([$request])
            );
        } catch (ApiException $e) {
            throw new KycSidecarUnavailableException(previous: $e);
        }

        $response = iterator_to_array($batchResponse->getResponses())[0];

        if ($response->hasError()) {
            throw new KycSidecarUnavailableException($response->getError()->getMessage());
        }

        return $response;
    }

    /**
     * Built from a base64-encoded service-account JSON key stored directly
     * in config/env, rather than a key file on disk - keeps the credential
     * out of the filesystem entirely, local or deployed.
     */
    private function credentials(): ServiceAccountCredentials
    {
        $keyJson = json_decode(base64_decode((string) config('services.cloud_vision.key_base64')), true);

        if (! is_array($keyJson)) {
            throw new KycSidecarUnavailableException('GOOGLE_CLOUD_VISION_KEY_BASE64 is missing or not valid base64-encoded JSON.');
        }

        return new ServiceAccountCredentials(['https://www.googleapis.com/auth/cloud-platform'], $keyJson);
    }
}

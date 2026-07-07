<?php

namespace App\Jobs;

use App\Contracts\Kyc\FaceMatchClientContract;
use App\Contracts\Kyc\OcrClientContract;
use App\Enums\ProfessionalApplicationStatus;
use App\Events\ProfessionalApplicationStatusChanged;
use App\Exceptions\KycSidecarUnavailableException;
use App\Models\ProfessionalApplication;
use App\Services\Kyc\IdVerifierResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Throwable;

class ProcessProfessionalApplication implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    public $tries = 3;

    /** @var array<int, int> */
    public $backoff = [10, 30, 60];

    private const DISK = 's3';

    public function __construct(public readonly int $applicationId) {}

    public function handle(OcrClientContract $ocrClient, FaceMatchClientContract $faceMatchClient, IdVerifierResolver $idVerifierResolver): void
    {
        $application = ProfessionalApplication::find($this->applicationId);

        if (! $application || $application->isTerminal()) {
            return;
        }

        try {
            $fullText = $ocrClient->detectText(self::DISK, $application->id_photo_path);
        } catch (KycSidecarUnavailableException) {
            $application->forceFill([
                'status' => ProfessionalApplicationStatus::PendingReview,
                'verification_notes' => 'OCR service unavailable; manual review required.',
            ])->save();

            event(new ProfessionalApplicationStatusChanged($application));

            return;
        }

        if (trim($fullText) === '') {
            $this->autoReject($application, 'ID unreadable — no text detected.');

            return;
        }

        $extracted = $idVerifierResolver->resolve($application->id_type)->extractFields($fullText);

        $application->forceFill([
            'ocr_extracted_data' => $extracted,
            'ocr_raw_response' => ['full_text' => $fullText],
            'profession' => $extracted['profession'],
            'specialty' => $extracted['specialty'],
            'license_number' => $extracted['license_number'],
            'license_expiry' => $extracted['license_expiry'],
            'full_name_on_id' => $extracted['full_name'],
        ])->save();

        if (blank($extracted['profession']) || blank($extracted['license_number'])) {
            $this->autoReject($application, 'ID fields unreadable/incomplete.');

            return;
        }

        if (filled($application->selfie_frame_paths)) {
            try {
                $liveness = $faceMatchClient->checkLiveness(
                    self::DISK,
                    $application->selfie_frame_paths,
                    $application->liveness_flash_frames ?? []
                );
            } catch (KycSidecarUnavailableException) {
                $application->forceFill([
                    'status' => ProfessionalApplicationStatus::PendingReview,
                    'verification_notes' => 'Liveness check service unavailable; manual review required.',
                ])->save();

                event(new ProfessionalApplicationStatusChanged($application));

                return;
            }

            $application->forceFill([
                'liveness_score' => $liveness['score'],
                'liveness_passed' => $liveness['live'],
            ])->save();

            if (! $liveness['live']) {
                $reason = match (true) {
                    ! $liveness['blink_detected'] && ! $liveness['color_reflection_passed'] => 'Liveness check failed — no blink detected and the on-screen flash did not reflect on the face.',
                    ! $liveness['blink_detected'] => 'Liveness check failed — no blink detected.',
                    default => 'Liveness check failed — the on-screen color flash did not reflect on the face as expected.',
                };

                $this->autoReject($application, $reason);

                return;
            }
        }

        try {
            $faceMatch = $faceMatchClient->compare(self::DISK, $application->selfie_path, $application->id_photo_path);
        } catch (KycSidecarUnavailableException) {
            $application->forceFill([
                'status' => ProfessionalApplicationStatus::PendingReview,
                'verification_notes' => 'Face-match service unavailable; manual review required.',
            ])->save();

            event(new ProfessionalApplicationStatusChanged($application));

            return;
        }

        if ((int) Arr::get($faceMatch, 'faces_detected.source', 0) === 0 || (int) Arr::get($faceMatch, 'faces_detected.target', 0) === 0) {
            $this->autoReject($application, 'No face detected in selfie/ID photo.');

            return;
        }

        $score = (float) $faceMatch['score'];
        $passed = $score >= (float) config('kyc.face_match_hard_floor');

        $application->forceFill([
            'face_match_score' => $score,
            'face_match_passed' => $passed,
        ])->save();

        if (! $passed) {
            $this->autoReject($application, 'Face match score below minimum threshold.');

            return;
        }

        $application->forceFill(['status' => ProfessionalApplicationStatus::PendingReview])->save();

        event(new ProfessionalApplicationStatusChanged($application));
    }

    public function failed(?Throwable $exception): void
    {
        $application = ProfessionalApplication::find($this->applicationId);

        if (! $application || $application->isTerminal()) {
            return;
        }

        $application->forceFill([
            'status' => ProfessionalApplicationStatus::PendingReview,
            'verification_notes' => 'Automatic verification failed after retries; manual review required.',
        ])->save();

        event(new ProfessionalApplicationStatusChanged($application));
    }

    private function autoReject(ProfessionalApplication $application, string $reason): void
    {
        $application->forceFill([
            'status' => ProfessionalApplicationStatus::AutoRejected,
            'rejection_reason' => $reason,
        ])->save();

        event(new ProfessionalApplicationStatusChanged($application));
    }
}

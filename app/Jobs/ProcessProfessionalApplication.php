<?php

namespace App\Jobs;

use App\Contracts\Kyc\FaceMatchClientContract;
use App\Contracts\Kyc\OcrClientContract;
use App\Enums\WorkflowStatus;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

        // Fetched once and reused below (OCR + face-match both need the ID
        // photo; the selfie's last frame doubles as both a liveness burst
        // frame and the face-match source) rather than re-fetching the same
        // S3 objects a second time per step.
        $idPhotoContents = Storage::disk(self::DISK)->get($application->id_photo_path);

        if ($idPhotoContents === null) {
            $this->markPendingReview($application, 'ID photo file missing from storage; manual review required.');

            return;
        }

        try {
            $fullText = $ocrClient->detectText($idPhotoContents);
        } catch (KycSidecarUnavailableException $e) {
            $this->logKycFailure($application, 'OCR', $e);
            $this->recordFailureNote($application, 'OCR service unavailable; manual review required.');

            throw $e;
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
            'license_number' => $extracted['license_number'],
            'license_expiry' => $extracted['license_expiry'],
            'full_name_on_id' => $extracted['full_name'],
        ])->save();

        // Profession alone no longer gates here - some valid PRC card
        // variants don't print a profession field at all (see
        // PrcIdVerifier::extractProfession()), so a blank profession still
        // goes on to liveness/face-match rather than auto-rejecting; an
        // admin can judge profession from the ID photo during manual review.
        if (blank($extracted['license_number'])) {
            $this->autoReject($application, 'ID fields unreadable/incomplete.');

            return;
        }

        // Keyed by path so the selfie_path frame (always the last one, see
        // ProfessionalApplicationService::submit()) can be reused for the
        // face-match call below instead of being fetched from S3 twice.
        $frameContentsByPath = collect($application->selfie_frame_paths ?? [])
            ->mapWithKeys(fn (string $path) => [$path => Storage::disk(self::DISK)->get($path)]);

        if ($frameContentsByPath->contains(null)) {
            $this->markPendingReview($application, 'Liveness frame file missing from storage; manual review required.');

            return;
        }

        if ($frameContentsByPath->isNotEmpty()) {
            $flashFrameContents = collect($application->liveness_flash_frames ?? [])
                ->map(fn (array $flashFrame) => [
                    'contents' => Storage::disk(self::DISK)->get($flashFrame['path']),
                    'color' => $flashFrame['color'],
                ])
                ->all();

            if (collect($flashFrameContents)->pluck('contents')->contains(null)) {
                $this->markPendingReview($application, 'Liveness flash frame file missing from storage; manual review required.');

                return;
            }

            try {
                $liveness = $faceMatchClient->checkLiveness($frameContentsByPath->values()->all(), $flashFrameContents);
            } catch (KycSidecarUnavailableException $e) {
                $this->logKycFailure($application, 'liveness check', $e);
                $this->recordFailureNote($application, 'Liveness check service unavailable; manual review required.');

                throw $e;
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

        $selfieContents = $frameContentsByPath->get($application->selfie_path)
            ?? Storage::disk(self::DISK)->get($application->selfie_path);

        if ($selfieContents === null) {
            $this->markPendingReview($application, 'Selfie file missing from storage; manual review required.');

            return;
        }

        try {
            $faceMatch = $faceMatchClient->compare($selfieContents, $idPhotoContents);
        } catch (KycSidecarUnavailableException $e) {
            $this->logKycFailure($application, 'face match', $e);
            $this->recordFailureNote($application, 'Face-match service unavailable; manual review required.');

            throw $e;
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

        // Clears any note a prior failed attempt left behind (e.g. a
        // transient Vision error retried successfully) so it doesn't
        // linger on an application that ultimately succeeded cleanly.
        $application->forceFill(['status' => WorkflowStatus::PendingReview, 'verification_notes' => null])->save();

        $this->broadcastStatusChanged($application);
    }

    /**
     * Runs once $tries is exhausted (see recordFailureNote()/the `throw $e`
     * after each KYC-service catch below, which is what lets Laravel's own
     * $tries/$backoff retry a transient failure - e.g. Vision billing still
     * propagating - a couple times before giving up here).
     */
    public function failed(?Throwable $exception): void
    {
        $application = ProfessionalApplication::find($this->applicationId);

        if (! $application || $application->isTerminal()) {
            return;
        }

        $note = $application->verification_notes ?: 'Automatic verification failed after retries; manual review required.';

        $this->markPendingReview($application, $note);
    }

    /**
     * The user-facing status only ever says "service unavailable" - this is
     * what actually goes to laravel.log so the real cause (bad credentials,
     * Vision API error, quota/billing) is diagnosable without reproducing
     * the failure by hand.
     */
    private function logKycFailure(ProfessionalApplication $application, string $step, KycSidecarUnavailableException $e): void
    {
        Log::warning("KYC {$step} failed for professional application #{$application->id}", [
            'message' => $e->getMessage(),
            'previous' => $e->getPrevious()?->getMessage(),
        ]);
    }

    /**
     * Records which step failed without changing status - status only
     * changes once $tries is exhausted (failed(), above), since this note
     * gets overwritten by a later successful retry otherwise.
     */
    private function recordFailureNote(ProfessionalApplication $application, string $note): void
    {
        $application->forceFill(['verification_notes' => $note])->save();
    }

    private function markPendingReview(ProfessionalApplication $application, string $note): void
    {
        $application->forceFill([
            'status' => WorkflowStatus::PendingReview,
            'verification_notes' => $note,
        ])->save();

        $this->broadcastStatusChanged($application);
    }

    private function autoReject(ProfessionalApplication $application, string $reason): void
    {
        $application->forceFill([
            'status' => WorkflowStatus::AutoRejected,
            'rejection_reason' => $reason,
        ])->save();

        $this->broadcastStatusChanged($application);
    }

    private function broadcastStatusChanged(ProfessionalApplication $application): void
    {
        event(new ProfessionalApplicationStatusChanged($application->id, $application->user_id, $application->status->value));
    }
}

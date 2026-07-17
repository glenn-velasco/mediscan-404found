<?php

namespace App\Jobs;

use App\Contracts\Kyc\FaceMatchClientContract;
use App\Contracts\Kyc\OcrClientContract;
use App\Exceptions\KycSidecarUnavailableException;
use App\Models\AccountRetrievalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Runs OCR + face-match as decision support only, same shape as the deleted
 * ProcessMedicalInformationLinkRequest job - this never approves or denies a
 * retrieval request itself, it only annotates the row for the admin
 * reviewer. No autoReject(): an automated false rejection would block
 * someone's only path back into their own account.
 */
class ProcessAccountRetrievalRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    public $tries = 3;

    /** @var array<int, int> */
    public $backoff = [10, 30, 60];

    private const DISK = 's3';

    public function __construct(public readonly int $retrievalRequestId) {}

    public function handle(OcrClientContract $ocrClient, FaceMatchClientContract $faceMatchClient): void
    {
        $retrievalRequest = AccountRetrievalRequest::find($this->retrievalRequestId);

        if (! $retrievalRequest || $retrievalRequest->isTerminal()) {
            return;
        }

        $idPhotoContents = Storage::disk(self::DISK)->get($retrievalRequest->id_photo_path);
        $selfieContents = Storage::disk(self::DISK)->get($retrievalRequest->selfie_path);

        if ($idPhotoContents === null || $selfieContents === null) {
            $this->annotate($retrievalRequest, 'ID photo or selfie missing from storage; manual review required.');

            return;
        }

        try {
            $text = $ocrClient->detectText($idPhotoContents);
            $retrievalRequest->forceFill(['ocr_extracted_data' => $text])->save();
        } catch (KycSidecarUnavailableException) {
            $this->annotate($retrievalRequest, 'OCR service unavailable; manual-only review.');
        }

        try {
            $faceMatch = $faceMatchClient->compare($selfieContents, $idPhotoContents);
        } catch (KycSidecarUnavailableException) {
            $this->annotate($retrievalRequest, 'Face-match service unavailable; manual-only review.');

            return;
        }

        if ((int) Arr::get($faceMatch, 'faces_detected.source', 0) === 0 || (int) Arr::get($faceMatch, 'faces_detected.target', 0) === 0) {
            $this->annotate($retrievalRequest, 'No face detected in selfie/ID photo; manual review required.');

            return;
        }

        $score = (float) $faceMatch['score'];
        $passed = $score >= (float) config('kyc.account_retrieval_face_match_floor');

        $retrievalRequest->forceFill([
            'face_match_score' => $score,
            'face_match_passed' => $passed,
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        $retrievalRequest = AccountRetrievalRequest::find($this->retrievalRequestId);

        if (! $retrievalRequest || $retrievalRequest->isTerminal()) {
            return;
        }

        $this->annotate($retrievalRequest, 'Automatic verification failed after retries; manual review required.');
    }

    private function annotate(AccountRetrievalRequest $retrievalRequest, string $note): void
    {
        $retrievalRequest->forceFill(['verification_notes' => $note])->save();
    }
}

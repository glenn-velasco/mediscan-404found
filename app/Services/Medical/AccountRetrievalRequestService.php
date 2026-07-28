<?php

namespace App\Services\Medical;

use App\Enums\AuditLogType;
use App\Enums\WorkflowStatus;
use App\Events\AccountRetrievalRequestStatusChanged;
use App\Jobs\ProcessAccountRetrievalRequest;
use App\Models\AccountRetrievalRequest;
use App\Models\User;
use App\Notifications\AccountRetrievalRequestStatusNotification;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AccountRetrievalRequestService
{
    private const DISK = 's3';

    public function __construct(
        private MedicalInformationService $medicalInformationService,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * Submits an account retrieval request. Reachable both pre-registration
     * (no account yet - $requester is null) and from a logged-in fresh
     * account. Looks up the old account by email and cross-checks the
     * submitted name+dob against its medical record as a corroborating
     * signal for the admin - a mismatch doesn't block submission, it's just
     * recorded for the reviewer. Response is identical to the caller
     * regardless of whether old_email matched anything (anti-enumeration,
     * same rule the deleted link-request flow followed).
     *
     * @param  array{old_email: string, first_name: string, middle_name: ?string, last_name: string, dob: string}  $data
     */
    public function submit(?User $requester, array $data, UploadedFile $idPhoto, UploadedFile $selfie): void
    {
        $folder = 'account-retrieval-requests/'.($requester === null ? 'guest' : $requester->id).'/'.Str::uuid();
        $idPhotoPath = $idPhoto->store($folder, self::DISK);
        $selfiePath = $selfie->store($folder, self::DISK);

        $verificationNotes = $this->crossCheckNotes($data);

        ProcessAccountRetrievalRequest::dispatch(
            AccountRetrievalRequest::create([
                'requester_user_id' => $requester?->id,
                'old_email' => $data['old_email'],
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'dob' => $data['dob'],
                'id_photo_path' => $idPhotoPath,
                'selfie_path' => $selfiePath,
                'verification_notes' => $verificationNotes,
                'status' => WorkflowStatus::Pending,
            ])->id
        )->afterCommit();
    }

    /**
     * @param  array{old_email: string, first_name: string, middle_name: ?string, last_name: string, dob: string}  $data
     */
    private function crossCheckNotes(array $data): ?string
    {
        $oldUser = User::where('email', $data['old_email'])->first();

        if (! $oldUser || ! $oldUser->medicalInformation) {
            return null;
        }

        $record = $oldUser->medicalInformation;
        $mismatches = [];

        if (mb_strtolower($record->first_name) !== mb_strtolower($data['first_name'])) {
            $mismatches[] = 'first name';
        }

        if (mb_strtolower($record->last_name) !== mb_strtolower($data['last_name'])) {
            $mismatches[] = 'last name';
        }

        if ($record->dob->toDateString() !== $data['dob']) {
            $mismatches[] = 'date of birth';
        }

        return $mismatches === []
            ? null
            : 'Submitted '.implode(', ', $mismatches).' does not match the record on file for the given old_email.';
    }

    /**
     * Admin-approved: repoints the requester's current account onto the
     * candidate record found via old_email, or - when the request was
     * submitted pre-registration ($requester_user_id is null, so there's no
     * fresh account to repoint) - emails old_email a standard password
     * reset link so they can regain access to the existing account.
     */
    public function approve(AccountRetrievalRequest $retrievalRequest, User $admin): void
    {
        if ($retrievalRequest->isTerminal()) {
            return;
        }

        $oldUser = User::where('email', $retrievalRequest->old_email)->first();
        $repointed = false;

        if ($retrievalRequest->requester_user_id !== null) {
            $requester = $retrievalRequest->requester;

            if ($oldUser?->medicalInformation) {
                $this->medicalInformationService->repointUserToRecord($requester, $oldUser->medicalInformation);
                $repointed = true;
            }
        } elseif ($oldUser) {
            Password::sendResetLink(['email' => $oldUser->email]);
        }

        $retrievalRequest->forceFill([
            'status' => WorkflowStatus::Approved,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ])->save();

        $this->notifyRequester($retrievalRequest);

        $this->auditLogger->log(
            action: 'account_retrieval_request.approved',
            type: AuditLogType::Accepted,
            actor: $admin,
            subject: $retrievalRequest->requester ?? $oldUser,
            metadata: [
                'account_retrieval_request_id' => $retrievalRequest->id,
                'repointed_existing_account' => $repointed,
            ],
            channel: 'web',
        );

        AccountRetrievalRequestStatusChanged::dispatch(
            $retrievalRequest->id,
            $retrievalRequest->requester_user_id,
            WorkflowStatus::Approved->value,
        );
    }

    public function deny(AccountRetrievalRequest $retrievalRequest, User $admin, string $reason): void
    {
        if ($retrievalRequest->isTerminal()) {
            return;
        }

        $retrievalRequest->forceFill([
            'status' => WorkflowStatus::Denied,
            'rejection_reason' => $reason,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ])->save();

        $this->notifyRequester($retrievalRequest);

        $this->auditLogger->log(
            action: 'account_retrieval_request.denied',
            type: AuditLogType::Denied,
            actor: $admin,
            subject: $retrievalRequest->requester,
            metadata: ['account_retrieval_request_id' => $retrievalRequest->id],
            channel: 'web',
        );

        AccountRetrievalRequestStatusChanged::dispatch(
            $retrievalRequest->id,
            $retrievalRequest->requester_user_id,
            WorkflowStatus::Denied->value,
        );
    }

    /**
     * Notify the requester of the outcome. Only sent when the request
     * was submitted from a registered account (requester_user_id is set).
     * Pre-registration requests are notified via the password-reset flow
     * on approve, and silently ignored on deny (anti-enumeration).
     */
    private function notifyRequester(AccountRetrievalRequest $retrievalRequest): void
    {
        if ($retrievalRequest->requester_user_id === null) {
            return;
        }

        $retrievalRequest->requester->notify(
            new AccountRetrievalRequestStatusNotification($retrievalRequest)
        );
    }
}

<?php

namespace App\Services\Medical;

use App\Enums\AuditLogType;
use App\Enums\WorkflowStatus;
use App\Events\MedicalInformationRegistrationMatchStatusChanged;
use App\Models\MedicalInformationRegistrationMatch;
use App\Models\User;
use App\Notifications\MedicalInformationRegistrationMatchNotification;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class MedicalInformationRegistrationMatchService
{
    public function __construct(
        private MedicalInformationService $medicalInformationService,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * Called at the end of registration. Looks for a single, unambiguous
     * name+dob match against an already-claimed (primary-owned) record and,
     * if found, notifies that record's primary user out-of-band (email,
     * signed accept/deny links - no admin in the loop). Deliberately silent
     * (no return value) otherwise: no match, an ambiguous match, or a match
     * against a record with no primary yet all leave the registrant on
     * their fresh interim record with no visible difference.
     *
     * @param  array{first_name: string, middle_name: ?string, last_name: string, suffix: ?string}  $nameFields
     */
    public function detectAndNotify(User $requester, array $nameFields, string $dob): void
    {
        $candidate = $this->medicalInformationService->findLinkCandidate($nameFields, $dob);

        if ($candidate === null || $candidate->id === $requester->medical_information_id || $candidate->primary_user_id === null) {
            return;
        }

        $match = MedicalInformationRegistrationMatch::create([
            'requester_user_id' => $requester->id,
            'candidate_medical_information_id' => $candidate->id,
            'status' => WorkflowStatus::Pending,
        ]);

        $primary = $candidate->primaryUser;
        $primary->notify(new MedicalInformationRegistrationMatchNotification($match));

        $this->auditLogger->log(
            action: 'medical_information_registration_match.notified',
            type: AuditLogType::Create,
            actor: $primary,
            subject: $requester,
            metadata: ['medical_information_id' => $candidate->id, 'match_id' => $match->id],
            channel: 'api',
        );
    }

    /**
     * The primary user confirming the registrant is legitimately them:
     * repoints the requester's account onto the shared record and discards
     * their interim record.
     */
    public function accept(MedicalInformationRegistrationMatch $match): void
    {
        if ($match->isTerminal()) {
            return;
        }

        DB::transaction(function () use ($match) {
            $requester = $match->requester;
            $candidate = $match->candidate;

            $this->medicalInformationService->repointUserToRecord($requester, $candidate);

            $match->forceFill([
                'status' => WorkflowStatus::Approved,
                'responded_at' => now(),
            ])->save();

            $this->auditLogger->log(
                action: 'medical_information_registration_match.accepted',
                type: AuditLogType::Accepted,
                actor: $candidate->primaryUser,
                subject: $requester,
                metadata: ['medical_information_id' => $candidate->id, 'match_id' => $match->id],
                channel: 'web',
            );

            MedicalInformationRegistrationMatchStatusChanged::dispatch(
                $match->id,
                $requester->id,
                WorkflowStatus::Approved->value,
            );
        });
    }

    /**
     * The primary user denying the registrant is them: the requester
     * silently keeps their interim record. No notification to the
     * requester - that would confirm the matched record's existence to
     * them, which is exactly what this whole flow is designed to avoid.
     */
    public function deny(MedicalInformationRegistrationMatch $match): void
    {
        if ($match->isTerminal()) {
            return;
        }

        $candidate = $match->candidate;

        $match->forceFill([
            'status' => WorkflowStatus::Denied,
            'responded_at' => now(),
        ])->save();

        $this->auditLogger->log(
            action: 'medical_information_registration_match.denied',
            type: AuditLogType::Denied,
            actor: $candidate->primaryUser,
            subject: $match->requester,
            metadata: ['medical_information_id' => $candidate->id, 'match_id' => $match->id],
            channel: 'web',
        );

        MedicalInformationRegistrationMatchStatusChanged::dispatch(
            $match->id,
            $match->requester_user_id,
            WorkflowStatus::Denied->value,
        );
    }
}

<?php

namespace App\Services\Medical;

use App\Enums\AuditLogType;
use App\Enums\Role;
use App\Enums\WorkflowStatus;
use App\Events\MedicalInformationRegistrationMatchCreated;
use App\Models\MedicalInformation;
use App\Models\MedicalInformationRegistrationMatch;
use App\Models\PendingRegistration;
use App\Notifications\MedicalInformationRegistrationMatchNotification;
use App\Notifications\PendingRegistrationConfirmedNotification;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class MedicalInformationRegistrationMatchService
{
    public function __construct(
        private MedicalInformationService $medicalInformationService,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * Called once CreateNewUser has already found an unambiguous name+dob
     * match against an already-claimed (primary-owned) record - creates the
     * match row referencing the held PendingRegistration (no User/
     * MedicalInformation exists yet for this registrant) and notifies the
     * candidate's primary user out-of-band (email, accept link only - see
     * MedicalInformationRegistrationMatchNotification for why there's no
     * deny link).
     */
    public function createForPendingRegistration(PendingRegistration $pendingRegistration, MedicalInformation $candidate): MedicalInformationRegistrationMatch
    {
        $match = MedicalInformationRegistrationMatch::create([
            'pending_registration_id' => $pendingRegistration->id,
            'candidate_medical_information_id' => $candidate->id,
            'status' => WorkflowStatus::Pending,
        ]);

        $primary = $candidate->primaryUser;
        $primary->notify(new MedicalInformationRegistrationMatchNotification($match));

        MedicalInformationRegistrationMatchCreated::dispatch($match->id, $primary->id);

        $this->auditLogger->log(
            action: 'medical_information_registration_match.notified',
            type: AuditLogType::Create,
            actor: $primary,
            metadata: [
                'medical_information_id' => $candidate->id,
                'match_id' => $match->id,
                'pending_registration_id' => $pendingRegistration->id,
            ],
            channel: 'api',
        );

        return $match;
    }

    /**
     * The primary user confirming the registrant is legitimately them:
     * materializes a real account directly onto the shared record (no
     * interim record ever existed, so there's nothing to merge/discard -
     * unlike repointUserToRecord(), used elsewhere for already-existing
     * accounts). Since the new registrant has no session to notify in-app,
     * emails them that they're confirmed and can now log in.
     */
    public function accept(MedicalInformationRegistrationMatch $match): void
    {
        if ($match->isTerminal()) {
            return;
        }

        DB::transaction(function () use ($match) {
            $candidate = $match->candidate;
            $pendingRegistration = $match->pendingRegistration;

            $user = $this->medicalInformationService->materializeUserOntoRecord($pendingRegistration, $candidate);
            $user->assignRole(Role::User->value);

            metric('signups')->category(Role::User->value)->hourly()->record();

            $match->forceFill([
                'status' => WorkflowStatus::Approved,
                'responded_at' => now(),
            ])->save();

            $this->auditLogger->log(
                action: 'medical_information_registration_match.accepted',
                type: AuditLogType::Accepted,
                actor: $candidate->primaryUser,
                subject: $user,
                metadata: ['medical_information_id' => $candidate->id, 'match_id' => $match->id],
                channel: 'web',
            );

            $email = $pendingRegistration->email;
            $pendingRegistration->delete();

            Notification::route('mail', $email)->notify(new PendingRegistrationConfirmedNotification);
        });
    }

    public function deny(MedicalInformationRegistrationMatch $match): void
    {
        $this->resolveAsRejected($match, WorkflowStatus::Denied, 'medical_information_registration_match.denied');
    }

    /**
     * Auto-resolves matches nobody ever acted on - the email only offers an
     * accept action (see MedicalInformationRegistrationMatchNotification;
     * there's no deny link to click, just "ignore this if it wasn't you"),
     * so a registration that really was denied-by-inaction would otherwise
     * sit staged forever. Called by the registration-matches:expire-stale
     * scheduled command.
     *
     * @return int number of matches expired
     */
    public function expireStale(int $days): int
    {
        $stale = MedicalInformationRegistrationMatch::query()
            ->where('status', WorkflowStatus::Pending)
            ->where('created_at', '<=', now()->subDays($days))
            ->get();

        foreach ($stale as $match) {
            $this->resolveAsRejected($match, WorkflowStatus::Expired, 'medical_information_registration_match.expired');
        }

        return $stale->count();
    }

    /**
     * Shared terminal-resolution path for deny() and expireStale(): mark
     * the match resolved and discard the staged registration data - no
     * account/tokens/interim record was ever created, so there's nothing
     * else to clean up. No notification is ever sent on this path - that
     * would confirm the matched record's existence to whoever submitted
     * the registration, which is exactly what this flow avoids.
     */
    private function resolveAsRejected(MedicalInformationRegistrationMatch $match, WorkflowStatus $status, string $auditAction): void
    {
        if ($match->isTerminal()) {
            return;
        }

        $candidate = $match->candidate;
        $pendingRegistration = $match->pendingRegistration;

        $match->forceFill([
            'status' => $status,
            'responded_at' => now(),
        ])->save();

        $this->auditLogger->log(
            action: $auditAction,
            type: $status === WorkflowStatus::Expired ? AuditLogType::Expired : AuditLogType::Denied,
            actor: $candidate->primaryUser,
            metadata: [
                'medical_information_id' => $candidate->id,
                'match_id' => $match->id,
                'pending_registration_id' => $pendingRegistration?->id,
            ],
            channel: 'web',
        );

        $pendingRegistration?->delete();
    }
}

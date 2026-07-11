<?php

namespace App\Services\Sync;

use App\Enums\AuditLogType;
use App\Events\PendingSyncEnvelopeCreated;
use App\Models\PendingSyncEnvelope;
use App\Models\User;
use App\Models\UserDeviceKey;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class ProfessionalSyncService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Get the patient's current active device public key.
     * Returns null if no key is registered.
     */
    public function patientPublicKey(User $patient): ?string
    {
        $key = UserDeviceKey::where('user_id', $patient->id)
            ->where('is_active', true)
            ->latest('registered_at')
            ->first();

        return $key?->public_key;
    }

    /**
     * Submit an encrypted envelope for a patient.
     * The ciphertext is already sealed client-side to the patient's public key.
     */
    public function submitEnvelope(
        User $professional,
        User $patient,
        string $ciphertext,
        string $envelopeType,
    ): PendingSyncEnvelope {
        return DB::transaction(function () use ($professional, $patient, $ciphertext, $envelopeType) {
            $envelope = PendingSyncEnvelope::create([
                'sender_id' => $professional->id,
                'recipient_id' => $patient->id,
                'envelope_type' => $envelopeType,
                'ciphertext' => $ciphertext,
                'expires_at' => now()->addDays(90),
            ]);

            $this->auditLogger->log(
                action: 'envelope.submitted',
                type: AuditLogType::Create,
                actor: $professional,
                subject: $patient,
                metadata: [
                    'envelope_id' => $envelope->id,
                    'envelope_type' => $envelopeType,
                    'expires_at' => $envelope->expires_at,
                ],
                channel: 'api',
            );

            event(new PendingSyncEnvelopeCreated(
                envelopeId: $envelope->id,
                recipientId: $patient->id,
                envelopeType: $envelopeType,
            ));

            return $envelope;
        });
    }
}

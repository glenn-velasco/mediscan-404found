<?php

namespace App\Services\Sync;

use App\Enums\AuditLogType;
use App\Enums\WorkflowStatus;
use App\Models\PendingSyncEnvelope;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PendingSyncService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Get all pending envelopes for a user, ordered by creation time.
     *
     * @return Collection<int, PendingSyncEnvelope>
     */
    public function pendingFor(User $user): Collection
    {
        return PendingSyncEnvelope::where('recipient_id', $user->id)
            ->where('status', WorkflowStatus::Pending->value)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Mark an envelope as acknowledged by the recipient.
     */
    public function acknowledge(User $user, PendingSyncEnvelope $envelope): void
    {
        if ($envelope->recipient_id !== $user->id) {
            abort(404);
        }

        DB::transaction(function () use ($user, $envelope) {
            $envelope->update([
                'status' => WorkflowStatus::Acknowledged->value,
                'acknowledged_at' => now(),
            ]);

            $this->auditLogger->log(
                action: 'envelope.acknowledged',
                type: AuditLogType::Accepted,
                actor: $user,
                subject: $envelope->sender,
                metadata: [
                    'envelope_id' => $envelope->id,
                    'envelope_type' => $envelope->envelope_type,
                ],
                channel: 'api',
            );
        });
    }

    /**
     * Expire stale envelopes (for scheduled command).
     */
    public function expireStale(): void
    {
        $expired = PendingSyncEnvelope::where('status', WorkflowStatus::Pending->value)
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expired as $envelope) {
            DB::transaction(function () use ($envelope) {
                $envelope->update(['status' => WorkflowStatus::Expired->value]);

                $this->auditLogger->log(
                    action: 'envelope.expired',
                    type: AuditLogType::Denied,
                    actor: null,
                    subject: $envelope->recipient,
                    metadata: [
                        'envelope_id' => $envelope->id,
                        'envelope_type' => $envelope->envelope_type,
                        'expires_at' => $envelope->expires_at,
                    ],
                    channel: 'system',
                );
            });
        }
    }
}

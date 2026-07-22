<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired on create/update/delete of an allergy, diagnosis, medication, or
 * emergency contact - one shared event (not four near-identical ones) since
 * every consumer reaction is the same: "something in this user's patient
 * data changed, go re-pull it" (see docs/SYNC.md and docs/BROADCASTING.md).
 */
class PatientRecordUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(
        public readonly string $recordType,
        public readonly string $recordId,
        public readonly int $userId,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("App.Models.User.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'PatientRecordUpdated';
    }

    /**
     * Metadata only - no PHI, consistent with `MedicalInformationUpdated`'s
     * convention.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'record_type' => $this->recordType,
            'record_id' => $this->recordId,
        ];
    }
}

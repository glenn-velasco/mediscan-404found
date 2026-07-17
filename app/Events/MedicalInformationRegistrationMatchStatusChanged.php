<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Self-broadcasts on the requester's own channel when a registration match
 * is accepted or denied by the record's primary user - metadata only (no
 * PHI), same shape as MedicalInformationUpdated. There is no admin-dashboard
 * broadcast here: this flow never involves an admin. See docs/BROADCASTING.md.
 */
class MedicalInformationRegistrationMatchStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public bool $afterCommit = true;

    public function __construct(
        public readonly int $matchId,
        public readonly int $requesterUserId,
        public readonly string $status,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("App.Models.User.{$this->requesterUserId}")];
    }

    public function broadcastAs(): string
    {
        return 'MedicalInformationRegistrationMatchStatusChanged';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'medical_information_registration_match_id' => $this->matchId,
            'status' => $this->status,
        ];
    }
}

<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Broadcasts on the candidate record's primary user's own channel when a
 * registration match is created, so an already-open mobile session can
 * surface a notification-bell entry immediately rather than waiting for
 * the email. Metadata only (no PHI). See docs/BROADCASTING.md.
 */
class MedicalInformationRegistrationMatchCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public bool $afterCommit = true;

    public function __construct(
        public readonly int $matchId,
        public readonly int $primaryUserId,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("App.Models.User.{$this->primaryUserId}")];
    }

    public function broadcastAs(): string
    {
        return 'MedicalInformationRegistrationMatchCreated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'medical_information_registration_match_id' => $this->matchId,
        ];
    }
}

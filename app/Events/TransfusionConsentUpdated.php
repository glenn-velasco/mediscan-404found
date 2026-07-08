<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when the patient records a transfusion decision or a professional
 * witnesses one. Self-broadcasts on the patient's own channel as
 * `TransfusionConsentUpdated`. See docs/BROADCASTING.md.
 */
class TransfusionConsentUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public bool $afterCommit = true;

    public function __construct(
        public readonly int $userId,
        public readonly bool $noBloodTransfusion,
        public readonly int $witnessCount,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("App.Models.User.{$this->userId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TransfusionConsentUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'no_blood_transfusion' => $this->noBloodTransfusion,
            'witness_count' => $this->witnessCount,
        ];
    }
}

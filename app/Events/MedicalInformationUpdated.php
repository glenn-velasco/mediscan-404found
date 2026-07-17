<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MedicalInformationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    /**
     * @param  array<int, int>  $linkedUserIds  every user currently linked to
     *                                          this medical_information row -
     *                                          the record can back many
     *                                          accounts, so all of them need
     *                                          to be woken up, not just one
     */
    public function __construct(
        public readonly int $medicalInformationId,
        public readonly array $linkedUserIds,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return array_map(
            fn (int $userId) => new PrivateChannel("App.Models.User.{$userId}"),
            $this->linkedUserIds
        );
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'MedicalInformationUpdated';
    }

    /**
     * Get the data to broadcast. Metadata only - no PHI fields, consistent
     * with the encrypted-relay convention elsewhere in this app
     * (`PendingSyncEnvelopeCreated` only ever sends `{envelope_id, envelope_type}`).
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'medical_information_id' => $this->medicalInformationId,
        ];
    }
}

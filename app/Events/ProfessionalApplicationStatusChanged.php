<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Self-broadcasts on the applicant's own channel and `admin-dashboard` as
 * `ProfessionalApplicationStatusChanged`. See docs/BROADCASTING.md.
 *
 * Takes plain values rather than the `ProfessionalApplication` model itself -
 * a rejected application is soft-deleted, and queuing a model reference would
 * mean the broadcast job has to restore a trashed row from the database
 * (relying on `Model::newQueryForRestoration()` bypassing the soft-delete
 * scope) instead of just carrying the id/status it already knows.
 */
class ProfessionalApplicationStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public bool $afterCommit = true;

    public function __construct(
        public readonly int $applicationId,
        public readonly int $userId,
        public readonly string $status,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("App.Models.User.{$this->userId}"),
            new PrivateChannel('admin-dashboard'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ProfessionalApplicationStatusChanged';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'application_id' => $this->applicationId,
            'status' => $this->status,
        ];
    }
}

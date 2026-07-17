<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Self-broadcasts on `admin-dashboard` (so the admin queue list live-updates
 * without a refresh) and, when the request was submitted from a logged-in
 * account, on that requester's own channel too. Pre-registration
 * submissions ($requesterUserId null) only broadcast to admin-dashboard -
 * there's no account yet to notify. See docs/BROADCASTING.md.
 */
class AccountRetrievalRequestStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public bool $afterCommit = true;

    public function __construct(
        public readonly int $retrievalRequestId,
        public readonly ?int $requesterUserId,
        public readonly string $status,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('admin-dashboard')];

        if ($this->requesterUserId !== null) {
            $channels[] = new PrivateChannel("App.Models.User.{$this->requesterUserId}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'AccountRetrievalRequestStatusChanged';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'account_retrieval_request_id' => $this->retrievalRequestId,
            'status' => $this->status,
        ];
    }
}

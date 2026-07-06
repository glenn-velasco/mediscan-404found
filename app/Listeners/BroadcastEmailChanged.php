<?php

namespace App\Listeners;

use App\Events\EmailChanged;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Broadcast;

class BroadcastEmailChanged implements ShouldQueueAfterCommit
{
    public function handle(EmailChanged $event): void
    {
        Broadcast::on([
            new PrivateChannel('admin-dashboard'),
            new PrivateChannel('App.Models.User.'.$event->user->id),
        ])
            ->as('EmailChanged')
            ->with(['user_id' => $event->user->id])
            ->send();
    }
}

<?php

namespace App\Listeners;

use App\Events\RoleChanged;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Broadcast;

class BroadcastRoleChanged implements ShouldQueueAfterCommit
{
    public function handle(RoleChanged $event): void
    {
        Broadcast::on([
            new PrivateChannel('admin-dashboard'),
            new PrivateChannel('App.Models.User.'.$event->user->id),
        ])
            ->as('RoleChanged')
            ->with(['user_id' => $event->user->id])
            ->send();
    }
}

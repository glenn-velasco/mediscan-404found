<?php

namespace App\Listeners;

use App\Events\EmailChanged;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Broadcast;

class BroadcastEmailChanged implements ShouldQueueAfterCommit
{
    public function handle(EmailChanged $event): void
    {
        Broadcast::private('admin-dashboard')
            ->as('EmailChanged')
            ->with(['user_id' => $event->user->id])
            ->send();
    }
}

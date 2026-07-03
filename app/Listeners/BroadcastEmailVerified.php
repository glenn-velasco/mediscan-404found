<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Broadcast;

class BroadcastEmailVerified implements ShouldQueueAfterCommit
{
    public function handle(Verified $event): void
    {
        Broadcast::private('App.Models.User.'.$event->user->getKey())
            ->as('EmailVerified')
            ->with(['email_verified_at' => $event->user->email_verified_at])
            ->send();
    }
}

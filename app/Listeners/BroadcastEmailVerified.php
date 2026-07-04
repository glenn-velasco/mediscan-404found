<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Broadcast;

class BroadcastEmailVerified implements ShouldQueueAfterCommit
{
    public function handle(Verified $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        Broadcast::private('App.Models.User.'.$user->getKey())
            ->as('EmailVerified')
            ->with(['email_verified_at' => $user->email_verified_at])
            ->send();
    }
}

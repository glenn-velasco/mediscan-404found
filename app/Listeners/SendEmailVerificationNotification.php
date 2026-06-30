<?php

namespace App\Listeners;

use App\Events\EmailChanged;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

class SendEmailVerificationNotification implements ShouldQueueAfterCommit
{
    public function handle(EmailChanged $event): void
    {
        $event->user->sendEmailVerificationNotification();
    }
}

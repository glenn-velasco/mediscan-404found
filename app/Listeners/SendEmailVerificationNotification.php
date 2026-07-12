<?php

namespace App\Listeners;

use App\Events\EmailChanged;
use App\Notifications\Api\VerifyApiEmail;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendEmailVerificationNotification implements ShouldQueue
{
    public function handle(EmailChanged $event): void
    {
        if ($event->origin === 'api') {
            $event->user->notify(new VerifyApiEmail);

            return;
        }

        $event->user->sendEmailVerificationNotification();
    }
}

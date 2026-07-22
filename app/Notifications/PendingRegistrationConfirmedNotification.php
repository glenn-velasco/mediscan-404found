<?php

namespace App\Notifications;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to a just-accepted registrant's email once their held registration is
 * confirmed - they have no account/session yet at this point (nothing was
 * ever created until acceptance), so this is the only way they learn it's
 * safe to log in. Sent via an on-demand Notification::route('mail', ...),
 * same pattern as UserInvitationNotification, since there's no User/
 * Notifiable model to notify through.
 */
class PendingRegistrationConfirmedNotification extends Notification implements ShouldQueueAfterCommit
{
    use SerializesModels;

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name');

        return (new MailMessage)
            ->subject("You're confirmed - log in to {$appName}")
            ->greeting('Hello!')
            ->line('Good news - your registration has been confirmed.')
            ->line("Open the {$appName} app and log in with the email and password you registered with.");
    }
}

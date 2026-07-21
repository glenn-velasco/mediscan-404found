<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProfessionalApplicationDeniedNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(private string $reason) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name');

        return (new MailMessage)
            ->subject('Your professional application has been denied')
            ->greeting('Hello,')
            ->line("Your professional application to {$appName} has been reviewed and was not approved.")
            ->line("Reason: {$this->reason}")
            ->line('You may submit a new application once you have addressed the issue above.');
    }
}

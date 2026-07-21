<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProfessionalApplicationApprovedNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(private string $roleGranted) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name');

        return (new MailMessage)
            ->subject('Your professional application has been approved')
            ->greeting('Congratulations!')
            ->line("Your professional application to {$appName} has been reviewed and approved.")
            ->line("You've been granted the {$this->roleGranted} role.")
            ->line('You can now access the features available to verified professionals.');
    }
}

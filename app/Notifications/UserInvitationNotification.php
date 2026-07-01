<?php

namespace App\Notifications;

use App\Enums\Role;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitationNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(
        private string $inviteUrl,
        private CarbonInterface $expire_at,
        private string $role
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name');

        return (new MailMessage)
            ->subject("You've been invited to {$appName}")
            ->greeting('Hello!')
            ->line("You have been invited to join {$appName}, a secure medical records management platform")
            ->action('Accept Invitation as '.Role::from($this->role)->label(), $this->inviteUrl)
            ->line("This invitation link expires in {$this->expire_at->diffForHumans()}.")
            ->line('If you did not expect this invitation, you may ignore this email.');
    }
}

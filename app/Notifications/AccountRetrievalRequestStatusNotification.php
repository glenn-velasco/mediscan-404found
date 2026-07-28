<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\AccountRetrievalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class AccountRetrievalRequestStatusNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable, SerializesModels;

    public function __construct(private AccountRetrievalRequest $retrievalRequest) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expires = Carbon::now()->addDays(7);
        $status = $this->retrievalRequest->status->value;

        $url = URL::temporarySignedRoute(
            'api.v1.account-retrieval.show',
            $expires,
            ['accountRetrievalRequest' => $this->retrievalRequest->id],
        );

        if ($status === 'approved') {
            return (new MailMessage)
                ->subject('Your medical information has been recovered')
                ->greeting('Hello!')
                ->line('An admin has approved your account retrieval request.')
                ->line('Your medical information is now linked to your account.')
                ->action('Continue', $url)
                ->line('Open the app to view your recovered medical information.');
        }

        return (new MailMessage)
            ->subject('Your account retrieval request was denied')
            ->greeting('Hello!')
            ->line('Unfortunately your account retrieval request has been denied.')
            ->line('Reason: '.($this->retrievalRequest->rejection_reason ?? 'No reason provided.'))
            ->action('Continue', $url)
            ->line('You may submit a new request if you believe this was an error.');
    }
}

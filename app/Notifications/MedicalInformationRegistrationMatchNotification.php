<?php

namespace App\Notifications;

use App\Models\MedicalInformationRegistrationMatch;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class MedicalInformationRegistrationMatchNotification extends Notification implements ShouldQueueAfterCommit
{
    use SerializesModels;

    public function __construct(private MedicalInformationRegistrationMatch $match) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expires = Carbon::now()->addDays(MedicalInformationRegistrationMatch::PENDING_DAYS);

        $acceptUrl = URL::temporarySignedRoute(
            'api.v1.registration-matches.accept',
            $expires,
            ['registrationMatch' => $this->match->id],
        );

        // Deliberately no deny link/action here - denial-by-inaction is the only path from
        // email (see registration-matches:expire-stale), so there's nothing to click if it
        // wasn't you. A deny link would also be a one-click way for anyone who merely
        // intercepts/forwards this email to affirmatively act on someone else's account; "ignore
        // it" has no such risk.
        return (new MailMessage)
            ->subject('Someone registered claiming to be linked to your medical record')
            ->greeting('Hello!')
            ->line('Someone just registered for an account with the same name and date of birth as the one on your medical record.')
            ->line('Was this you?')
            ->action('Yes, this was me', $acceptUrl)
            ->line("If this wasn't you, no action is needed - you can safely ignore this email.")
            ->line("This link expires in {$expires->diffForHumans()}, after which the registration will be automatically declined if nobody confirms it.");
    }
}

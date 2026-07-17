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
        $expires = Carbon::now()->addDays(7);

        $acceptUrl = URL::temporarySignedRoute(
            'medical-information-registration-matches.accept',
            $expires,
            ['medicalInformationRegistrationMatch' => $this->match->id],
        );

        $denyUrl = URL::temporarySignedRoute(
            'medical-information-registration-matches.deny',
            $expires,
            ['medicalInformationRegistrationMatch' => $this->match->id],
        );

        return (new MailMessage)
            ->subject('Someone registered claiming to be linked to your medical record')
            ->greeting('Hello!')
            ->line('Someone just registered for an account with the same name and date of birth as the one on your medical record.')
            ->line('Was this you?')
            ->action('Yes, this was me', $acceptUrl)
            ->line("If this was not you, deny the request instead: {$denyUrl}")
            ->line("This link expires in {$expires->diffForHumans()}.");
    }
}

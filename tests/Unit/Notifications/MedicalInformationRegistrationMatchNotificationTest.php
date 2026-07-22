<?php

use App\Models\MedicalInformation;
use App\Models\MedicalInformationRegistrationMatch;
use App\Models\User;
use App\Notifications\MedicalInformationRegistrationMatchNotification;

it('renders mail with a working signed accept URL and no deny link, without throwing', function () {
    $primary = User::factory()->create();
    $candidate = MedicalInformation::factory()->create(['primary_user_id' => $primary->id]);
    $requester = User::factory()->create();
    $match = MedicalInformationRegistrationMatch::factory()->create([
        'requester_user_id' => $requester->id,
        'candidate_medical_information_id' => $candidate->id,
    ]);

    $notification = new MedicalInformationRegistrationMatchNotification($match);

    // Catches route-parameter-name mismatches (e.g. passing a key that doesn't match the
    // {registrationMatch} route binding) that Notification::fake()-based tests can't, since
    // fake() never actually calls toMail() and so never triggers URL generation.
    $mail = $notification->toMail($primary);

    $allLines = [...$mail->introLines, ...$mail->outroLines];

    expect($mail->actionUrl)->toContain('/medical-information-registration-matches/'.$match->id.'/accept')
        ->and($mail->actionUrl)->toContain('signature=')
        ->and(collect($allLines)->contains(fn (string $line) => str_contains($line, '/deny')))->toBeFalse()
        ->and(collect($allLines)->contains(fn (string $line) => str_contains($line, 'ignore')))->toBeTrue();
});

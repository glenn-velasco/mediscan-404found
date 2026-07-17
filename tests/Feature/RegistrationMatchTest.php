<?php

use App\Events\MedicalInformationRegistrationMatchStatusChanged;
use App\Models\MedicalInformation;
use App\Models\MedicalInformationRegistrationMatch;
use App\Models\User;
use App\Notifications\MedicalInformationRegistrationMatchNotification;
use App\Services\Medical\MedicalInformationRegistrationMatchService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

it('notifies the primary user when a new registration matches their primary-owned record', function () {
    Notification::fake();

    $primary = User::factory()->create();
    $record = MedicalInformation::factory()->create([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'dob' => '1990-01-01',
        'primary_user_id' => $primary->id,
    ]);
    $primary->forceFill(['medical_information_id' => $record->id])->save();

    $registrant = User::factory()->create([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'dob' => '1990-01-01',
    ]);
    $interim = MedicalInformation::factory()->create(['primary_user_id' => $registrant->id]);
    $registrant->forceFill(['medical_information_id' => $interim->id])->save();

    app(MedicalInformationRegistrationMatchService::class)->detectAndNotify($registrant, [
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'suffix' => null,
    ], '1990-01-01');

    expect(MedicalInformationRegistrationMatch::count())->toBe(1);
    Notification::assertSentTo($primary, MedicalInformationRegistrationMatchNotification::class);
});

it('does nothing when the matched record has no primary user yet', function () {
    Notification::fake();

    MedicalInformation::factory()->create([
        'first_name' => 'Ana',
        'last_name' => 'Reyes',
        'dob' => '1985-05-05',
        'primary_user_id' => null,
    ]);

    $registrant = User::factory()->create([
        'first_name' => 'Ana',
        'last_name' => 'Reyes',
        'dob' => '1985-05-05',
    ]);

    app(MedicalInformationRegistrationMatchService::class)->detectAndNotify($registrant, [
        'first_name' => 'Ana',
        'middle_name' => null,
        'last_name' => 'Reyes',
        'suffix' => null,
    ], '1985-05-05');

    expect(MedicalInformationRegistrationMatch::count())->toBe(0);
    Notification::assertNothingSent();
});

it('accepting a match repoints the requester and deletes their interim record', function () {
    Event::fake([MedicalInformationRegistrationMatchStatusChanged::class]);

    $primary = User::factory()->create();
    $record = MedicalInformation::factory()->create(['primary_user_id' => $primary->id]);
    $primary->forceFill(['medical_information_id' => $record->id])->save();

    $interim = MedicalInformation::factory()->create();
    $requester = User::factory()->create(['medical_information_id' => $interim->id]);

    $match = MedicalInformationRegistrationMatch::factory()->create([
        'requester_user_id' => $requester->id,
        'candidate_medical_information_id' => $record->id,
    ]);

    app(MedicalInformationRegistrationMatchService::class)->accept($match);

    expect($requester->fresh()->medical_information_id)->toBe($record->id)
        ->and(MedicalInformation::find($interim->id))->toBeNull()
        ->and($match->fresh()->status->value)->toBe('approved');

    Event::assertDispatched(MedicalInformationRegistrationMatchStatusChanged::class);
});

it('denying a match leaves the requester on their interim record', function () {
    $primary = User::factory()->create();
    $record = MedicalInformation::factory()->create(['primary_user_id' => $primary->id]);

    $interim = MedicalInformation::factory()->create();
    $requester = User::factory()->create(['medical_information_id' => $interim->id]);

    $match = MedicalInformationRegistrationMatch::factory()->create([
        'requester_user_id' => $requester->id,
        'candidate_medical_information_id' => $record->id,
    ]);

    app(MedicalInformationRegistrationMatchService::class)->deny($match);

    expect($requester->fresh()->medical_information_id)->toBe($interim->id)
        ->and($match->fresh()->status->value)->toBe('denied');
});

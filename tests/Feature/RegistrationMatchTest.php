<?php

use App\Enums\Role;
use App\Enums\WorkflowStatus;
use App\Events\MedicalInformationRegistrationMatchCreated;
use App\Models\MedicalInformation;
use App\Models\MedicalInformationRegistrationMatch;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Notifications\MedicalInformationRegistrationMatchNotification;
use App\Notifications\PendingRegistrationConfirmedNotification;
use App\Services\Medical\MedicalInformationRegistrationMatchService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

it('notifies the primary user when a pending registration matches their primary-owned record', function () {
    Notification::fake();
    Event::fake([MedicalInformationRegistrationMatchCreated::class]);

    $primary = User::factory()->create();
    $record = MedicalInformation::factory()->create([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'dob' => '1990-01-01',
        'primary_user_id' => $primary->id,
    ]);
    $primary->forceFill(['medical_information_id' => $record->id])->save();

    $pendingRegistration = PendingRegistration::factory()->create([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'dob' => '1990-01-01',
    ]);

    $match = app(MedicalInformationRegistrationMatchService::class)->createForPendingRegistration($pendingRegistration, $record);

    expect(MedicalInformationRegistrationMatch::count())->toBe(1)
        ->and($match->isForPendingRegistration())->toBeTrue()
        ->and($match->status->value)->toBe('pending');
    Notification::assertSentTo($primary, MedicalInformationRegistrationMatchNotification::class);
    Event::assertDispatched(MedicalInformationRegistrationMatchCreated::class);
});

it('accepting a match materializes a real account directly onto the candidate record and emails the registrant', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Notification::fake();

    $primary = User::factory()->create();
    $record = MedicalInformation::factory()->create(['primary_user_id' => $primary->id]);
    $primary->forceFill(['medical_information_id' => $record->id])->save();

    $pendingRegistration = PendingRegistration::factory()->create(['email' => 'juan@example.com']);
    $match = MedicalInformationRegistrationMatch::factory()->create([
        'pending_registration_id' => $pendingRegistration->id,
        'candidate_medical_information_id' => $record->id,
    ]);

    app(MedicalInformationRegistrationMatchService::class)->accept($match);

    $user = User::where('email', 'juan@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->medical_information_id)->toBe($record->id)
        ->and($user->hasRole(Role::User->value))->toBeTrue()
        ->and(PendingRegistration::find($pendingRegistration->id))->toBeNull()
        ->and($match->fresh())->toBeNull(); // cascade-deletes with the pending registration

    Notification::assertSentOnDemand(PendingRegistrationConfirmedNotification::class);
});

it('denying a match discards the staged registration - no account is ever created', function () {
    Notification::fake();

    $primary = User::factory()->create();
    $record = MedicalInformation::factory()->create(['primary_user_id' => $primary->id]);

    $pendingRegistration = PendingRegistration::factory()->create(['email' => 'juan@example.com']);
    $match = MedicalInformationRegistrationMatch::factory()->create([
        'pending_registration_id' => $pendingRegistration->id,
        'candidate_medical_information_id' => $record->id,
    ]);

    app(MedicalInformationRegistrationMatchService::class)->deny($match);

    expect(User::where('email', 'juan@example.com')->exists())->toBeFalse()
        ->and(PendingRegistration::find($pendingRegistration->id))->toBeNull()
        ->and(MedicalInformationRegistrationMatch::find($match->id))->toBeNull();

    // Silent - denial must never confirm the matched record's existence to the registrant.
    Notification::assertNothingSent();
});

it('expires and discards matches nobody responded to within the window, leaving newer ones alone', function () {
    $primary = User::factory()->create();
    $record = MedicalInformation::factory()->create(['primary_user_id' => $primary->id]);

    $stalePendingRegistration = PendingRegistration::factory()->create();
    $staleMatch = MedicalInformationRegistrationMatch::factory()->create([
        'pending_registration_id' => $stalePendingRegistration->id,
        'candidate_medical_information_id' => $record->id,
        'created_at' => now()->subDays(MedicalInformationRegistrationMatch::PENDING_DAYS)->subMinute(),
    ]);

    $freshPendingRegistration = PendingRegistration::factory()->create();
    $freshMatch = MedicalInformationRegistrationMatch::factory()->create([
        'pending_registration_id' => $freshPendingRegistration->id,
        'candidate_medical_information_id' => $record->id,
        'created_at' => now(),
    ]);

    $count = app(MedicalInformationRegistrationMatchService::class)->expireStale(MedicalInformationRegistrationMatch::PENDING_DAYS);

    expect($count)->toBe(1)
        ->and(PendingRegistration::find($stalePendingRegistration->id))->toBeNull()
        ->and(MedicalInformationRegistrationMatch::find($staleMatch->id))->toBeNull()
        ->and(PendingRegistration::find($freshPendingRegistration->id))->not->toBeNull()
        ->and($freshMatch->fresh()->status->value)->toBe('pending');
});

it("exposes an expires_at that reflects the match's creation date, and null once resolved", function () {
    $primary = User::factory()->create();
    $record = MedicalInformation::factory()->create(['primary_user_id' => $primary->id]);
    $match = MedicalInformationRegistrationMatch::factory()->create([
        'candidate_medical_information_id' => $record->id,
        'created_at' => now(),
    ]);

    expect($match->expires_at->toDateString())
        ->toBe(now()->addDays(MedicalInformationRegistrationMatch::PENDING_DAYS)->toDateString());

    $match->forceFill(['status' => WorkflowStatus::Approved])->save();

    expect($match->fresh()->expires_at)->toBeNull();
});

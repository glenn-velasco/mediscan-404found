<?php

use App\Models\MedicalInformation;
use App\Models\MedicalInformationRegistrationMatch;
use App\Models\PendingRegistration;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Notification;

it('lists pending matches awaiting the authenticated primary user\'s decision', function () {
    $primary = User::factory()->create();
    $record = MedicalInformation::factory()->create(['primary_user_id' => $primary->id]);

    $match = MedicalInformationRegistrationMatch::factory()->create([
        'candidate_medical_information_id' => $record->id,
    ]);

    $this->actingAs($primary)
        ->getJson('/api/v1/medical-information-registration-matches')
        ->assertOk()
        ->assertJsonPath('data.0.id', $match->id)
        ->assertJsonPath('data.0.status', 'pending')
        ->assertJsonMissingPath('data.0.pending_registration_id');
});

it('lets the candidate record\'s primary user accept a match via the API, materializing the account', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Notification::fake();

    $primary = User::factory()->create();
    $record = MedicalInformation::factory()->create(['primary_user_id' => $primary->id]);

    $pendingRegistration = PendingRegistration::factory()->create(['email' => 'juan@example.com']);
    $match = MedicalInformationRegistrationMatch::factory()->create([
        'pending_registration_id' => $pendingRegistration->id,
        'candidate_medical_information_id' => $record->id,
    ]);

    $this->actingAs($primary)
        ->postJson("/api/v1/medical-information-registration-matches/{$match->id}/accept")
        ->assertOk();

    $user = User::where('email', 'juan@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->medical_information_id)->toBe($record->id);
});

it('lets the candidate record\'s primary user deny a match via the API, discarding the staged registration', function () {
    $primary = User::factory()->create();
    $record = MedicalInformation::factory()->create(['primary_user_id' => $primary->id]);

    $pendingRegistration = PendingRegistration::factory()->create(['email' => 'juan@example.com']);
    $match = MedicalInformationRegistrationMatch::factory()->create([
        'pending_registration_id' => $pendingRegistration->id,
        'candidate_medical_information_id' => $record->id,
    ]);

    $this->actingAs($primary)
        ->postJson("/api/v1/medical-information-registration-matches/{$match->id}/deny")
        ->assertOk();

    expect(User::where('email', 'juan@example.com')->exists())->toBeFalse()
        ->and(PendingRegistration::find($pendingRegistration->id))->toBeNull();
});

it('returns 404 (not 403) when a non-primary user tries to accept or deny a match', function () {
    $primary = User::factory()->create();
    $record = MedicalInformation::factory()->create(['primary_user_id' => $primary->id]);

    $match = MedicalInformationRegistrationMatch::factory()->create([
        'candidate_medical_information_id' => $record->id,
    ]);

    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->postJson("/api/v1/medical-information-registration-matches/{$match->id}/accept")
        ->assertNotFound();

    $this->actingAs($stranger)
        ->postJson("/api/v1/medical-information-registration-matches/{$match->id}/deny")
        ->assertNotFound();

    expect($match->fresh()->status->value)->toBe('pending');
});

<?php

use App\Models\MedicalInformation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

function medicalInformationPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'dob' => '1990-01-01',
        'gender' => 'male',
        'blood_type' => 'o_positive',
        'religion' => 'catholic',
        'address' => [
            'province' => 'Metro Manila',
            'city' => 'Manila',
        ],
        'no_blood_transfusion' => false,
    ], $overrides);
}

it('creates medical information and links it to the authenticated user', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/medical-information', medicalInformationPayload())
        ->assertCreated()
        ->assertJsonPath('data.first_name', 'Juan');

    expect($user->fresh()->medical_information_id)->not->toBeNull();
});

it('shows the authenticated users own linked medical information via index', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/medical-information', medicalInformationPayload());

    $this->getJson('/api/v1/medical-information')
        ->assertOk()
        ->assertJsonPath('data.first_name', 'Juan');
});

it('returns 404 when viewing another users medical information', function () {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    Sanctum::actingAs($owner, ['*']);
    $this->postJson('/api/v1/medical-information', medicalInformationPayload());
    $medicalInformation = MedicalInformation::query()->firstOrFail();

    $other = User::factory()->create(['email_verified_at' => now()]);
    Sanctum::actingAs($other, ['*']);

    $this->getJson("/api/v1/medical-information/{$medicalInformation->id}")
        ->assertNotFound();
});

it('allows an unverified user to create medical information', function () {
    $user = User::factory()->unverified()->create();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/medical-information', medicalInformationPayload())
        ->assertCreated();
});

it('updates medical information with nested contacts and invalidates cache', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/medical-information', medicalInformationPayload());
    $medicalInformation = MedicalInformation::query()->firstOrFail();

    // warm the cache
    $this->getJson("/api/v1/medical-information/{$medicalInformation->id}")->assertOk();
    expect(Cache::has("medical_information.{$medicalInformation->id}"))->toBeTrue();

    $this->putJson("/api/v1/medical-information/{$medicalInformation->id}", [
        'contacts' => [
            ['name' => 'Maria Dela Cruz', 'relationship' => 'spouse', 'phone_number' => '+639171234567', 'phone_country_code' => 'PH'],
        ],
    ])
        ->assertOk()
        ->assertJsonCount(1, 'data.contacts');

    expect(Cache::has("medical_information.{$medicalInformation->id}"))->toBeFalse();
});

it('validates blood type against the enum', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/medical-information', medicalInformationPayload(['blood_type' => 'not_a_type']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['blood_type']);
});

it('deletes medical information owned by the authenticated user', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/medical-information', medicalInformationPayload());
    $medicalInformation = MedicalInformation::query()->firstOrFail();

    $this->deleteJson("/api/v1/medical-information/{$medicalInformation->id}")->assertOk();

    expect(MedicalInformation::find($medicalInformation->id))->toBeNull();
});

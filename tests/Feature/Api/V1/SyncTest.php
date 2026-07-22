<?php

use App\Models\Allergy;
use App\Models\MedicalInformation;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function actingAsSyncUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $medicalInformation = MedicalInformation::factory()->create();
    $user->forceFill(['medical_information_id' => $medicalInformation->id])->save();

    Sanctum::actingAs($user->fresh(), ['*']);

    return $user->fresh();
}

it('returns everything when since is omitted', function () {
    $user = actingAsSyncUser();
    Allergy::factory()->count(2)->create(['medical_information_id' => $user->medical_information_id]);

    $this->getJson('/api/v1/sync')
        ->assertOk()
        ->assertJsonCount(2, 'data.allergies')
        ->assertJsonPath('data.medical_information.id', $user->medical_information_id);
});

it('excludes records unchanged since the given timestamp', function () {
    $user = actingAsSyncUser();
    Allergy::factory()->create(['medical_information_id' => $user->medical_information_id]);

    $cutoff = now()->addMinute();

    $this->travel(2)->minutes();
    $newAllergy = Allergy::factory()->create(['medical_information_id' => $user->medical_information_id]);

    $this->getJson('/api/v1/sync?since='.urlencode($cutoff->toIso8601String()))
        ->assertOk()
        ->assertJsonCount(1, 'data.allergies')
        ->assertJsonPath('data.allergies.0.id', $newAllergy->id);
});

it('includes soft-deleted records changed since the given timestamp so the client can propagate the delete', function () {
    $user = actingAsSyncUser();
    $allergy = Allergy::factory()->create(['medical_information_id' => $user->medical_information_id]);

    $cutoff = now()->addMinute();
    $this->travel(2)->minutes();
    $allergy->delete();

    $response = $this->getJson('/api/v1/sync?since='.urlencode($cutoff->toIso8601String()))->assertOk();

    $response->assertJsonCount(1, 'data.allergies')
        ->assertJsonPath('data.allergies.0.id', $allergy->id);

    expect($response->json('data.allergies.0.deleted_at'))->not->toBeNull();
});

it('scopes sync results to the authenticated users own medical information', function () {
    Allergy::factory()->create(); // someone else's

    actingAsSyncUser();

    $this->getJson('/api/v1/sync')->assertOk()->assertJsonCount(0, 'data.allergies');
});

it('rejects an invalid since value', function () {
    actingAsSyncUser();

    $this->getJson('/api/v1/sync?since=not-a-date')->assertStatus(422);
});

<?php

use App\Models\MedicalInformation;
use App\Models\Medication;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function actingAsMedicationOwner(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $medicalInformation = MedicalInformation::factory()->create();
    $user->forceFill(['medical_information_id' => $medicalInformation->id])->save();

    Sanctum::actingAs($user->fresh(), ['*']);

    return $user->fresh();
}

it('creates a medication on the authenticated users own medical information', function () {
    $user = actingAsMedicationOwner();
    $id = (string) Str::uuid();

    $this->postJson('/api/v1/medications', [
        'id' => $id,
        'name' => 'Metformin',
        'dosage' => '500mg',
        'frequency' => 'Twice daily',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Metformin');

    expect(Medication::query()->whereKey($id)->first()?->medical_information_id)->toBe($user->medical_information_id);
});

it('lists only the authenticated users own medications', function () {
    $user = actingAsMedicationOwner();
    Medication::factory()->count(2)->create(['medical_information_id' => $user->medical_information_id]);
    Medication::factory()->create();

    $this->getJson('/api/v1/medications')->assertOk()->assertJsonCount(2, 'data');
});

it('returns 404 for a medication owned by another user', function () {
    $medication = Medication::factory()->create();
    actingAsMedicationOwner();

    $this->getJson("/api/v1/medications/{$medication->id}")->assertNotFound();
});

it('updates an owned medication', function () {
    $user = actingAsMedicationOwner();
    $medication = Medication::factory()->create(['medical_information_id' => $user->medical_information_id]);

    $this->putJson("/api/v1/medications/{$medication->id}", [
        'name' => 'Lisinopril',
    ])->assertOk()->assertJsonPath('data.name', 'Lisinopril');
});

it('soft deletes an owned medication', function () {
    $user = actingAsMedicationOwner();
    $medication = Medication::factory()->create(['medical_information_id' => $user->medical_information_id]);

    $this->deleteJson("/api/v1/medications/{$medication->id}")->assertOk();

    expect(Medication::query()->whereKey($medication->id)->exists())->toBeFalse();
});

it('encrypts name and dosage at rest', function () {
    $user = actingAsMedicationOwner();
    $medication = Medication::factory()->create([
        'medical_information_id' => $user->medical_information_id,
        'name' => 'Metformin',
        'dosage' => '500mg',
    ]);

    $raw = DB::table('medications')->where('id', $medication->id)->first();

    expect($raw->name)->not->toContain('Metformin')
        ->and($raw->dosage)->not->toContain('500mg');
});

<?php

use App\Models\Diagnosis;
use App\Models\MedicalInformation;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function actingAsDiagnosisOwner(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $medicalInformation = MedicalInformation::factory()->create();
    $user->forceFill(['medical_information_id' => $medicalInformation->id])->save();

    Sanctum::actingAs($user->fresh(), ['*']);

    return $user->fresh();
}

it('creates a diagnosis on the authenticated users own medical information', function () {
    $user = actingAsDiagnosisOwner();
    $id = (string) Str::uuid();

    $this->postJson('/api/v1/diagnoses', [
        'id' => $id,
        'condition' => 'Type 2 Diabetes',
        'date_of_diagnosis' => '2020-05-01',
        'severity' => 'chronic',
    ])
        ->assertCreated()
        ->assertJsonPath('data.condition', 'Type 2 Diabetes');

    expect(Diagnosis::query()->whereKey($id)->first()?->medical_information_id)->toBe($user->medical_information_id);
});

it('lists only the authenticated users own diagnoses', function () {
    $user = actingAsDiagnosisOwner();
    Diagnosis::factory()->count(2)->create(['medical_information_id' => $user->medical_information_id]);
    Diagnosis::factory()->create();

    $this->getJson('/api/v1/diagnoses')->assertOk()->assertJsonCount(2, 'data');
});

it('returns 404 for a diagnosis owned by another user', function () {
    $diagnosis = Diagnosis::factory()->create();
    actingAsDiagnosisOwner();

    $this->getJson("/api/v1/diagnoses/{$diagnosis->id}")->assertNotFound();
});

it('updates an owned diagnosis', function () {
    $user = actingAsDiagnosisOwner();
    $diagnosis = Diagnosis::factory()->create(['medical_information_id' => $user->medical_information_id]);

    $this->putJson("/api/v1/diagnoses/{$diagnosis->id}", [
        'condition' => 'Hypertension',
        'severity' => 'ongoing',
    ])->assertOk()->assertJsonPath('data.condition', 'Hypertension');
});

it('soft deletes an owned diagnosis', function () {
    $user = actingAsDiagnosisOwner();
    $diagnosis = Diagnosis::factory()->create(['medical_information_id' => $user->medical_information_id]);

    $this->deleteJson("/api/v1/diagnoses/{$diagnosis->id}")->assertOk();

    expect(Diagnosis::query()->whereKey($diagnosis->id)->exists())->toBeFalse();
});

it('encrypts condition at rest', function () {
    $user = actingAsDiagnosisOwner();
    $diagnosis = Diagnosis::factory()->create([
        'medical_information_id' => $user->medical_information_id,
        'condition' => 'Type 2 Diabetes',
    ]);

    $raw = DB::table('diagnoses')->where('id', $diagnosis->id)->first();

    expect($raw->condition)->not->toContain('Type 2 Diabetes');
});

<?php

use App\Enums\Permission as PermissionEnum;
use App\Models\Diagnosis;
use App\Models\MedicalInformation;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

beforeEach(fn () => $this->seed(RoleAndPermissionSeeder::class));

function actingAsDiagnosisOwner(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $medicalInformation = MedicalInformation::factory()->create();
    $user->forceFill(['medical_information_id' => $medicalInformation->id])->save();

    Sanctum::actingAs($user->fresh(), ['*']);

    return $user->fresh();
}

function makeLinkedUser(MedicalInformation $medicalInformation): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->forceFill(['medical_information_id' => $medicalInformation->id])->save();

    return $user->fresh();
}

function makeVerifiedProfessional(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);

    $role = Role::findOrCreate('doctor', 'web');
    $role->givePermissionTo(PermissionEnum::VerifiedProfessional->value);
    $user->assignRole($role);

    return $user->fresh();
}

it('lets a verified professional linked to the record create a diagnosis', function () {
    $medicalInformation = MedicalInformation::factory()->create();
    $professional = makeVerifiedProfessional();
    $professional->forceFill(['medical_information_id' => $medicalInformation->id])->save();
    Sanctum::actingAs($professional->fresh(), ['*']);

    $id = (string) Str::uuid();

    $this->postJson("/api/v1/medical-information/{$medicalInformation->id}/diagnoses", [
        'id' => $id,
        'condition' => 'Type 2 Diabetes',
        'date_of_diagnosis' => '2020-05-01',
        'severity' => 'chronic',
    ])
        ->assertCreated()
        ->assertJsonPath('data.condition', 'Type 2 Diabetes')
        ->assertJsonPath('data.diagnosed_by.id', $professional->id);

    $diagnosis = Diagnosis::query()->whereKey($id)->first();
    expect($diagnosis->medical_information_id)->toBe($medicalInformation->id);
    expect($diagnosis->diagnosed_by)->toBe($professional->id);
});

it('forbids a plain linked user (non-professional) from creating a diagnosis', function () {
    $medicalInformation = MedicalInformation::factory()->create();
    $patient = makeLinkedUser($medicalInformation);
    Sanctum::actingAs($patient, ['*']);

    $this->postJson("/api/v1/medical-information/{$medicalInformation->id}/diagnoses", [
        'id' => (string) Str::uuid(),
        'condition' => 'Type 2 Diabetes',
    ])->assertNotFound();
});

it('forbids a verified professional not linked to the record from creating a diagnosis', function () {
    $medicalInformation = MedicalInformation::factory()->create();
    $professional = makeVerifiedProfessional();
    Sanctum::actingAs($professional, ['*']);

    $this->postJson("/api/v1/medical-information/{$medicalInformation->id}/diagnoses", [
        'id' => (string) Str::uuid(),
        'condition' => 'Type 2 Diabetes',
    ])->assertNotFound();
});

it('lists only the authenticated users own diagnoses', function () {
    $user = actingAsDiagnosisOwner();
    Diagnosis::factory()->count(2)->create(['medical_information_id' => $user->medical_information_id]);
    Diagnosis::factory()->create();

    $this->getJson('/api/v1/diagnoses')->assertOk()->assertJsonCount(2, 'data');
});

it('lets a patient view (but not author) their own diagnoses', function () {
    $user = actingAsDiagnosisOwner();
    $diagnosis = Diagnosis::factory()->create(['medical_information_id' => $user->medical_information_id]);

    $this->getJson("/api/v1/diagnoses/{$diagnosis->id}")->assertOk()->assertJsonPath('data.id', $diagnosis->id);
});

it('returns 404 for a diagnosis owned by another user', function () {
    $diagnosis = Diagnosis::factory()->create();
    actingAsDiagnosisOwner();

    $this->getJson("/api/v1/diagnoses/{$diagnosis->id}")->assertNotFound();
});

it('lets a linked verified professional update a diagnosis', function () {
    $medicalInformation = MedicalInformation::factory()->create();
    $diagnosis = Diagnosis::factory()->create(['medical_information_id' => $medicalInformation->id]);
    $professional = makeVerifiedProfessional();
    $professional->forceFill(['medical_information_id' => $medicalInformation->id])->save();
    Sanctum::actingAs($professional->fresh(), ['*']);

    $this->putJson("/api/v1/diagnoses/{$diagnosis->id}", [
        'condition' => 'Hypertension',
        'severity' => 'ongoing',
    ])->assertOk()->assertJsonPath('data.condition', 'Hypertension');
});

it('forbids a plain linked patient from updating a diagnosis', function () {
    $user = actingAsDiagnosisOwner();
    $diagnosis = Diagnosis::factory()->create(['medical_information_id' => $user->medical_information_id]);

    $this->putJson("/api/v1/diagnoses/{$diagnosis->id}", [
        'condition' => 'Hypertension',
    ])->assertNotFound();
});

it('lets a linked verified professional delete a diagnosis', function () {
    $medicalInformation = MedicalInformation::factory()->create();
    $diagnosis = Diagnosis::factory()->create(['medical_information_id' => $medicalInformation->id]);
    $professional = makeVerifiedProfessional();
    $professional->forceFill(['medical_information_id' => $medicalInformation->id])->save();
    Sanctum::actingAs($professional->fresh(), ['*']);

    $this->deleteJson("/api/v1/diagnoses/{$diagnosis->id}")->assertOk();

    expect(Diagnosis::query()->whereKey($diagnosis->id)->exists())->toBeFalse();
});

it('forbids a plain linked patient from deleting a diagnosis', function () {
    $user = actingAsDiagnosisOwner();
    $diagnosis = Diagnosis::factory()->create(['medical_information_id' => $user->medical_information_id]);

    $this->deleteJson("/api/v1/diagnoses/{$diagnosis->id}")->assertNotFound();
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

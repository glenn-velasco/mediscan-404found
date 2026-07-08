<?php

use App\Enums\Gender;
use App\Enums\Permission;
use App\Events\AllergyVerified;
use App\Events\TransfusionConsentUpdated;
use App\Models\Allergy;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->professional = function (): User {
        $user = User::factory()->create();

        $role = Role::firstOrCreate(['name' => 'cardiology', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::VerifiedProfessional->value);
        $user->assignRole($role);

        return $user;
    };

    $this->patientWithMedicalInfo = function (array $overrides = []): User {
        $user = User::factory()->create();

        $user->medicalInformation()->create(array_merge([
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'date_of_birth' => '1992-04-10',
            'gender' => Gender::Female,
            'no_blood_transfusion' => false,
        ], $overrides));

        return $user;
    };

    $this->allergyFor = function (User $user, array $overrides = []): Allergy {
        return $user->medicalInformation->allergies()->create(array_merge([
            'allergen' => 'Peanuts',
            'reaction' => 'Hives',
            'severity' => 'severe',
        ], $overrides));
    };
});

it('rejects tokens without the verified professional ability', function () {
    $patient = ($this->patientWithMedicalInfo)();
    Sanctum::actingAs(User::factory()->create(), ['basic']);

    $this->getJson("/api/v1/professional/patients/{$patient->id}")
        ->assertForbidden();
});

it('rejects a wildcard-ability token when the user lacks the permission', function () {
    $patient = ($this->patientWithMedicalInfo)();
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->getJson("/api/v1/professional/patients/{$patient->id}")
        ->assertForbidden();
});

it('looks up a patient by email with allergies and verification fields', function () {
    $professional = ($this->professional)();
    $patient = ($this->patientWithMedicalInfo)();
    ($this->allergyFor)($patient);

    Sanctum::actingAs($professional, $professional->tokenAbilities());

    $this->getJson('/api/v1/professional/patients?email='.urlencode($patient->email))
        ->assertOk()
        ->assertJsonPath('data.patient_id', $patient->id)
        ->assertJsonPath('data.medical_information.full_name', 'Ana Reyes')
        ->assertJsonPath('data.medical_information.allergies.0.allergen', 'Peanuts')
        ->assertJsonPath('data.medical_information.allergies.0.verified_at', null);
});

it('returns 404 for an unknown email', function () {
    $professional = ($this->professional)();
    Sanctum::actingAs($professional, $professional->tokenAbilities());

    $this->getJson('/api/v1/professional/patients?email=nobody@example.com')
        ->assertNotFound();
});

it('shows a patient by id', function () {
    $professional = ($this->professional)();
    $patient = ($this->patientWithMedicalInfo)();

    Sanctum::actingAs($professional, $professional->tokenAbilities());

    $this->getJson("/api/v1/professional/patients/{$patient->id}")
        ->assertOk()
        ->assertJsonPath('data.medical_information.no_blood_transfusion', false)
        ->assertJsonPath('data.medical_information.transfusion_decision_at', null);
});

it('verifies an allergy via the api', function () {
    Event::fake([AllergyVerified::class]);

    $professional = ($this->professional)();
    $patient = ($this->patientWithMedicalInfo)();
    $allergy = ($this->allergyFor)($patient);

    Sanctum::actingAs($professional, $professional->tokenAbilities());

    $this->postJson("/api/v1/professional/allergies/{$allergy->id}/verify")
        ->assertOk()
        ->assertJsonPath('data.verified_by', $professional->id)
        ->assertJsonPath('data.verified_by_name', $professional->name);

    $this->assertDatabaseHas('allergies', [
        'id' => $allergy->id,
        'verified_by' => $professional->id,
    ]);

    Event::assertDispatched(AllergyVerified::class, fn (AllergyVerified $event) => $event->userId === $patient->id
        && $event->allergyId === $allergy->id);
});

it('forbids verifying the professional\'s own allergy via the api', function () {
    $professional = ($this->professional)();
    $professional->medicalInformation()->create([
        'first_name' => 'Self',
        'last_name' => 'Attester',
        'date_of_birth' => '1980-01-01',
        'gender' => Gender::Male,
        'no_blood_transfusion' => false,
    ]);
    $allergy = ($this->allergyFor)($professional);

    Sanctum::actingAs($professional, $professional->tokenAbilities());

    $this->postJson("/api/v1/professional/allergies/{$allergy->id}/verify")
        ->assertForbidden();
});

it('witnesses a transfusion decision via the api', function () {
    Event::fake([TransfusionConsentUpdated::class]);

    $professional = ($this->professional)();
    $patient = ($this->patientWithMedicalInfo)(['no_blood_transfusion' => true]);
    $patient->medicalInformation->forceFill([
        'transfusion_decision_by' => $patient->id,
        'transfusion_decision_at' => now(),
    ])->save();

    Sanctum::actingAs($professional, $professional->tokenAbilities());

    $this->postJson("/api/v1/professional/patients/{$patient->id}/transfusion-witness")
        ->assertOk()
        ->assertJsonPath('data.transfusion_witnesses.0.user_id', $professional->id)
        ->assertJsonPath('data.transfusion_witnesses.0.name', $professional->name);

    Event::assertDispatched(TransfusionConsentUpdated::class, fn (TransfusionConsentUpdated $event) => $event->userId === $patient->id
        && $event->witnessCount === 1);
});

it('rejects witnessing before the patient records a decision', function () {
    $professional = ($this->professional)();
    $patient = ($this->patientWithMedicalInfo)();

    Sanctum::actingAs($professional, $professional->tokenAbilities());

    $this->postJson("/api/v1/professional/patients/{$patient->id}/transfusion-witness")
        ->assertStatus(422);
});

it('stamps transfusion decision attribution when updating medical information via the api', function () {
    $patient = ($this->patientWithMedicalInfo)();
    Sanctum::actingAs($patient, ['*']);

    $this->putJson('/api/v1/medical-information', [
        'first_name' => 'Ana',
        'last_name' => 'Reyes',
        'date_of_birth' => '1992-04-10',
        'gender' => 'female',
        'no_blood_transfusion' => true,
    ])->assertOk();

    $medicalInfo = $patient->medicalInformation->fresh();
    expect($medicalInfo->transfusion_decision_by)->toBe($patient->id)
        ->and($medicalInfo->transfusion_decision_at)->not->toBeNull();
});

<?php

use App\Enums\Gender;
use App\Enums\Permission;
use App\Models\Allergy;
use App\Models\User;
use App\Services\User\MedicalInfoService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
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

// --- Access control ---

it('redirects guests to login', function () {
    $this->get(route('professional.patients.index'))
        ->assertRedirect(route('login'));
});

it('forbids users without the verified professional permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('professional.patients.index'))
        ->assertForbidden();
});

it('allows a verified professional to view the lookup page', function () {
    $this->actingAs(($this->professional)())
        ->get(route('professional.patients.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('professional/patients/index'));
});

// --- Lookup ---

it('shows the patient inline when a patient is found by exact email', function () {
    $patient = ($this->patientWithMedicalInfo)();
    ($this->allergyFor)($patient);

    $this->actingAs(($this->professional)())
        ->get(route('professional.patients.index', ['email' => $patient->email]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('professional/patients/index')
            ->where('patient.id', $patient->id)
            ->where('patient.full_name', 'Ana Reyes')
            ->count('patient.allergies', 1));
});

it('returns a validation error for an unknown email', function () {
    $this->actingAs(($this->professional)())
        ->from(route('professional.patients.index'))
        ->get(route('professional.patients.index', ['email' => 'nobody@example.com']))
        ->assertRedirect(route('professional.patients.index'))
        ->assertSessionHasErrors('email');
});

it('returns a validation error when the patient has no medical information', function () {
    $patient = User::factory()->create();

    $this->actingAs(($this->professional)())
        ->from(route('professional.patients.index'))
        ->get(route('professional.patients.index', ['email' => $patient->email]))
        ->assertRedirect(route('professional.patients.index'))
        ->assertSessionHasErrors('email');
});

// --- Allergy verification ---

it('lets a professional verify a patient allergy', function () {
    $professional = ($this->professional)();
    $patient = ($this->patientWithMedicalInfo)();
    $allergy = ($this->allergyFor)($patient);

    Cache::put(MedicalInfoService::cacheKey($patient->id), ['stale'], now()->addMonth());

    $this->actingAs($professional)
        ->post(route('professional.allergies.verify', $allergy))
        ->assertRedirect();

    $this->assertDatabaseHas('allergies', [
        'id' => $allergy->id,
        'verified_by' => $professional->id,
    ]);

    expect($allergy->fresh()->verified_at)->not->toBeNull()
        ->and(Cache::has(MedicalInfoService::cacheKey($patient->id)))->toBeFalse();
});

it('forbids a professional from verifying their own allergy', function () {
    $professional = ($this->professional)();
    $professional->medicalInformation()->create([
        'first_name' => 'Self',
        'last_name' => 'Attester',
        'date_of_birth' => '1980-01-01',
        'gender' => Gender::Male,
        'no_blood_transfusion' => false,
    ]);
    $allergy = ($this->allergyFor)($professional);

    $this->actingAs($professional)
        ->post(route('professional.allergies.verify', $allergy))
        ->assertForbidden();
});

it('forbids a plain user from verifying an allergy', function () {
    $patient = ($this->patientWithMedicalInfo)();
    $allergy = ($this->allergyFor)($patient);

    $this->actingAs(User::factory()->create())
        ->post(route('professional.allergies.verify', $allergy))
        ->assertForbidden();
});

it('clears verification when the patient changes a verified allergy', function () {
    $professional = ($this->professional)();
    $patient = ($this->patientWithMedicalInfo)();
    $allergy = ($this->allergyFor)($patient, [
        'verified_by' => $professional->id,
        'verified_at' => now(),
    ]);

    $this->actingAs($patient)
        ->patch(route('allergies.update', $allergy), [
            'allergen' => 'Peanuts',
            'reaction' => 'Hives',
            'severity' => 'mild',
        ])
        ->assertRedirect();

    $allergy->refresh();
    expect($allergy->verified_by)->toBeNull()
        ->and($allergy->verified_at)->toBeNull();
});

it('keeps verification when the patient saves an allergy unchanged', function () {
    $professional = ($this->professional)();
    $patient = ($this->patientWithMedicalInfo)();
    $allergy = ($this->allergyFor)($patient, [
        'verified_by' => $professional->id,
        'verified_at' => now(),
    ]);

    $this->actingAs($patient)
        ->patch(route('allergies.update', $allergy), [
            'allergen' => 'Peanuts',
            'reaction' => 'Hives',
            'severity' => 'severe',
        ])
        ->assertRedirect();

    $allergy->refresh();
    expect($allergy->verified_by)->toBe($professional->id)
        ->and($allergy->verified_at)->not->toBeNull();
});

// --- Transfusion decision witnessing ---

it('rejects witnessing before the patient has recorded a decision', function () {
    $patient = ($this->patientWithMedicalInfo)();

    $this->actingAs(($this->professional)())
        ->post(route('professional.patients.transfusion-witness', $patient))
        ->assertStatus(422);
});

it('lets a professional witness a recorded transfusion decision', function () {
    $professional = ($this->professional)();
    $patient = ($this->patientWithMedicalInfo)([
        'no_blood_transfusion' => true,
    ]);
    $patient->medicalInformation->forceFill([
        'transfusion_decision_by' => $patient->id,
        'transfusion_decision_at' => now(),
    ])->save();

    $this->actingAs($professional)
        ->post(route('professional.patients.transfusion-witness', $patient))
        ->assertRedirect();

    $witnesses = $patient->medicalInformation->fresh()->transfusion_witnesses;
    expect($witnesses)->toHaveCount(1)
        ->and($witnesses[0]['user_id'])->toBe($professional->id)
        ->and($witnesses[0]['name'])->toBe($professional->name)
        ->and($witnesses[0]['witnessed_at'])->not->toBeNull();
});

it('lets multiple professionals witness the same decision', function () {
    $first = ($this->professional)();
    $second = ($this->professional)();
    $patient = ($this->patientWithMedicalInfo)([
        'no_blood_transfusion' => true,
    ]);
    $patient->medicalInformation->forceFill([
        'transfusion_decision_by' => $patient->id,
        'transfusion_decision_at' => now(),
    ])->save();

    $this->actingAs($first)
        ->post(route('professional.patients.transfusion-witness', $patient));
    $this->actingAs($second)
        ->post(route('professional.patients.transfusion-witness', $patient));

    $witnesses = $patient->medicalInformation->fresh()->transfusion_witnesses;
    expect($witnesses)->toHaveCount(2)
        ->and(array_column($witnesses, 'user_id'))->toBe([$first->id, $second->id]);
});

it('rejects a professional witnessing the same decision twice', function () {
    $professional = ($this->professional)();
    $patient = ($this->patientWithMedicalInfo)([
        'no_blood_transfusion' => true,
    ]);
    $patient->medicalInformation->forceFill([
        'transfusion_decision_by' => $patient->id,
        'transfusion_decision_at' => now(),
    ])->save();

    $this->actingAs($professional)
        ->post(route('professional.patients.transfusion-witness', $patient))
        ->assertRedirect();

    $this->actingAs($professional)
        ->post(route('professional.patients.transfusion-witness', $patient))
        ->assertStatus(422);

    expect($patient->medicalInformation->fresh()->transfusion_witnesses)->toHaveCount(1);
});

it('forbids a professional from witnessing their own decision', function () {
    $professional = ($this->professional)();
    $professional->medicalInformation()->create([
        'first_name' => 'Self',
        'last_name' => 'Attester',
        'date_of_birth' => '1980-01-01',
        'gender' => Gender::Male,
        'no_blood_transfusion' => true,
        'transfusion_decision_by' => $professional->id,
        'transfusion_decision_at' => now(),
    ]);

    $this->actingAs($professional)
        ->post(route('professional.patients.transfusion-witness', $professional))
        ->assertForbidden();
});

it('stamps the decision maker and resets the witnesses when the flag changes', function () {
    $professional = ($this->professional)();
    $patient = ($this->patientWithMedicalInfo)();
    $patient->medicalInformation->forceFill([
        'transfusion_decision_by' => $patient->id,
        'transfusion_decision_at' => now()->subDay(),
        'transfusion_witnesses' => [
            ['user_id' => $professional->id, 'name' => $professional->name, 'witnessed_at' => now()->subDay()->toIso8601String()],
        ],
    ])->save();

    $this->actingAs($patient)
        ->patch(route('dashboard.update'), [
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'date_of_birth' => '1992-04-10',
            'gender' => 'female',
            'no_blood_transfusion' => true,
        ])
        ->assertRedirect();

    $medicalInfo = $patient->medicalInformation->fresh();
    expect($medicalInfo->transfusion_decision_by)->toBe($patient->id)
        ->and($medicalInfo->transfusion_witnesses)->toBe([]);
});

it('keeps the witnesses when the flag is saved unchanged', function () {
    $professional = ($this->professional)();
    $patient = ($this->patientWithMedicalInfo)();
    $patient->medicalInformation->forceFill([
        'transfusion_decision_by' => $patient->id,
        'transfusion_decision_at' => now()->subDay(),
        'transfusion_witnesses' => [
            ['user_id' => $professional->id, 'name' => $professional->name, 'witnessed_at' => now()->subDay()->toIso8601String()],
        ],
    ])->save();

    $this->actingAs($patient)
        ->patch(route('dashboard.update'), [
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'date_of_birth' => '1992-04-10',
            'gender' => 'female',
            'no_blood_transfusion' => false,
        ])
        ->assertRedirect();

    $medicalInfo = $patient->medicalInformation->fresh();
    expect($medicalInfo->transfusion_witnesses)->toHaveCount(1);
});

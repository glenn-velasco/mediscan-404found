<?php

use App\Enums\Gender;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->medicalPayload = function (array $overrides = []): array {
        return array_merge([
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'date_of_birth' => '1992-04-10',
            'gender' => 'female',
            'blood_type' => 'A+',
            'no_blood_transfusion' => false,
        ], $overrides);
    };

    $this->withMedicalInfo = function (User $user): User {
        $user->medicalInformation()->create([
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'date_of_birth' => '1992-04-10',
            'gender' => Gender::Female,
            'no_blood_transfusion' => false,
        ]);

        return $user;
    };
});

it('creates medical information via api when none exists', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->putJson('/api/v1/medical-information', ($this->medicalPayload)())
        ->assertOk()
        ->assertJsonPath('data.full_name', 'Ana Reyes')
        ->assertJsonPath('data.blood_type', 'A+');

    expect($user->fresh()->medicalInformation)->not->toBeNull();
});

it('updates existing medical information via api', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    Sanctum::actingAs($user, ['*']);

    $this->putJson('/api/v1/medical-information', ($this->medicalPayload)([
        'first_name' => 'Maria',
        'religion' => 'Catholic',
    ]))
        ->assertOk()
        ->assertJsonPath('data.full_name', 'Maria Reyes')
        ->assertJsonPath('data.religion', 'Catholic');

    expect($user->fresh()->name)->toBe('Maria Reyes');
});

it('validates medical information payload via api', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->putJson('/api/v1/medical-information', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['first_name', 'last_name', 'date_of_birth', 'gender']);
});

it('users can add allergies via api', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/allergies', [
        'allergen' => 'Peanuts',
        'reaction' => 'Hives',
        'severity' => 'severe',
    ])
        ->assertCreated()
        ->assertJsonPath('data.allergen', 'Peanuts');

    $this->assertDatabaseHas('allergies', ['allergen' => 'Peanuts']);
});

it('cannot add allergies without medical information', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/allergies', [
        'allergen' => 'Peanuts',
        'severity' => 'severe',
    ])->assertNotFound();
});

it('users can remove their own allergies via api', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    $allergy = $user->medicalInformation->allergies()->create([
        'allergen' => 'Peanuts',
        'severity' => 'severe',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->deleteJson("/api/v1/allergies/{$allergy->id}")->assertOk();

    $this->assertDatabaseMissing('allergies', ['id' => $allergy->id]);
});

it('users cannot remove allergies belonging to another user', function () {
    $owner = ($this->withMedicalInfo)(User::factory()->create());
    $allergy = $owner->medicalInformation->allergies()->create([
        'allergen' => 'Peanuts',
        'severity' => 'severe',
    ]);

    $intruder = ($this->withMedicalInfo)(User::factory()->create());
    Sanctum::actingAs($intruder, ['*']);

    $this->deleteJson("/api/v1/allergies/{$allergy->id}")->assertNotFound();

    $this->assertDatabaseHas('allergies', ['id' => $allergy->id]);
});

it('returns not found when deleting a nonexistent allergy via api', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    $allergy = $user->medicalInformation->allergies()->create([
        'allergen' => 'Peanuts',
        'severity' => 'severe',
    ]);
    $deletedId = $allergy->id;
    $allergy->delete();

    Sanctum::actingAs($user, ['*']);

    $this->deleteJson("/api/v1/allergies/{$deletedId}")
        ->assertNotFound()
        ->assertJson(['status' => 404, 'message' => 'Not found.']);
});

it('users can update their own allergies via api', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    $allergy = $user->medicalInformation->allergies()->create([
        'allergen' => 'Peanuts',
        'reaction' => 'Hives',
        'severity' => 'severe',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->patchJson("/api/v1/allergies/{$allergy->id}", [
        'allergen' => 'Tree nuts',
        'reaction' => 'Swelling',
        'severity' => 'life-threatening',
    ])
        ->assertOk()
        ->assertJsonPath('data.allergen', 'Tree nuts');

    $this->assertDatabaseHas('allergies', [
        'id' => $allergy->id,
        'allergen' => 'Tree nuts',
        'severity' => 'life-threatening',
    ]);
});

it('update allergy validation errors via api', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    $allergy = $user->medicalInformation->allergies()->create([
        'allergen' => 'Peanuts',
        'severity' => 'severe',
    ]);
    Sanctum::actingAs($user, ['*']);

    $this->patchJson("/api/v1/allergies/{$allergy->id}", [
        'allergen' => 'Peanuts',
        'severity' => 'extreme',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['severity']);
});

it('users cannot update allergies belonging to another user via api', function () {
    $owner = ($this->withMedicalInfo)(User::factory()->create());
    $allergy = $owner->medicalInformation->allergies()->create([
        'allergen' => 'Peanuts',
        'severity' => 'severe',
    ]);

    $intruder = ($this->withMedicalInfo)(User::factory()->create());
    Sanctum::actingAs($intruder, ['*']);

    $this->patchJson("/api/v1/allergies/{$allergy->id}", [
        'allergen' => 'Hacked',
        'severity' => 'mild',
    ])->assertNotFound();

    $this->assertDatabaseHas('allergies', ['id' => $allergy->id, 'allergen' => 'Peanuts']);
});

it('returns not found when updating a nonexistent allergy via api', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    $allergy = $user->medicalInformation->allergies()->create([
        'allergen' => 'Peanuts',
        'severity' => 'severe',
    ]);
    $deletedId = $allergy->id;
    $allergy->delete();

    Sanctum::actingAs($user, ['*']);

    $this->patchJson("/api/v1/allergies/{$deletedId}", [
        'allergen' => 'Peanuts',
        'severity' => 'mild',
    ])
        ->assertNotFound()
        ->assertJson(['status' => 404, 'message' => 'Not found.']);
});

// --- Transfusion consent ---

it('records transfusion consent via the api', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    Sanctum::actingAs($user, ['*']);

    $this->patchJson('/api/v1/medical-information/transfusion-consent', [
        'no_blood_transfusion' => true,
    ])
        ->assertOk()
        ->assertJsonPath('data.no_blood_transfusion', true)
        ->assertJsonPath('data.transfusion_decision_by', $user->id);

    expect($user->medicalInformation->fresh()->transfusion_decision_at)->not->toBeNull();
});

it('resets the professional witnesses when consent changes via the api', function () {
    $witness = User::factory()->create();
    $user = ($this->withMedicalInfo)(User::factory()->create());
    $user->medicalInformation->forceFill([
        'no_blood_transfusion' => true,
        'transfusion_decision_by' => $user->id,
        'transfusion_decision_at' => now()->subDay(),
        'transfusion_witnesses' => [
            ['user_id' => $witness->id, 'name' => $witness->name, 'witnessed_at' => now()->subDay()->toIso8601String()],
        ],
    ])->save();

    Sanctum::actingAs($user, ['*']);

    $this->patchJson('/api/v1/medical-information/transfusion-consent', [
        'no_blood_transfusion' => false,
    ])
        ->assertOk()
        ->assertJsonPath('data.transfusion_witnesses', []);
});

it('validates the transfusion consent payload via the api', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    Sanctum::actingAs($user, ['*']);

    $this->patchJson('/api/v1/medical-information/transfusion-consent', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('no_blood_transfusion');
});

it('returns not found for transfusion consent without medical information', function () {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->patchJson('/api/v1/medical-information/transfusion-consent', [
        'no_blood_transfusion' => false,
    ])->assertNotFound();
});

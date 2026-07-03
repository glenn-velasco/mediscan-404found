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

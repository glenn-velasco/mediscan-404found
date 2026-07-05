<?php

use App\Enums\Gender;
use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

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

    $this->userWithMedicalInfo = function (): User {
        $user = User::factory()->create();
        $user->assignRole(Role::User->value);

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

it('guests cannot update medical information', function () {
    $this->patch(route('dashboard.update'), ($this->medicalPayload)())
        ->assertRedirect(route('login'));
});

it('user can create medical information via dashboard when none exists', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::User->value);

    $this->actingAs($user)
        ->patch(route('dashboard.update'), ($this->medicalPayload)())
        ->assertRedirect();

    $this->assertDatabaseHas('medical_information', [
        'user_id' => $user->id,
        'first_name' => 'Ana',
        'last_name' => 'Reyes',
        'blood_type' => 'A+',
    ]);
});

it('user can update existing medical information via dashboard', function () {
    $user = ($this->userWithMedicalInfo)();

    $this->actingAs($user)
        ->patch(route('dashboard.update'), ($this->medicalPayload)([
            'first_name' => 'Maria',
            'religion' => 'Catholic',
        ]))
        ->assertRedirect();

    $this->assertDatabaseHas('medical_information', [
        'user_id' => $user->id,
        'first_name' => 'Maria',
        'religion' => 'Catholic',
    ]);
});

it('update medical information via dashboard validation errors', function () {
    $user = ($this->userWithMedicalInfo)();

    $this->actingAs($user)
        ->patch(route('dashboard.update'), [])
        ->assertSessionHasErrors(['first_name', 'last_name', 'date_of_birth', 'gender']);
});

<?php

use App\Enums\Gender;
use App\Enums\Role;
use App\Models\Allergy;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->userWithMedicalInfo = function (): User {
        $user = User::factory()->create();
        $user->assignRole(Role::User->value);

        $user->medicalInformation()->create([
            'first_name'           => 'Ana',
            'last_name'            => 'Reyes',
            'date_of_birth'        => '1992-04-10',
            'gender'               => Gender::Female,
            'no_blood_transfusion' => false,
        ]);

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

// --- Create ---

it('guests cannot create allergy', function () {
    $this->post(route('allergies.store'), [
        'allergen' => 'Peanuts',
        'severity' => 'mild',
    ])->assertRedirect(route('login'));
});

it('user can create allergy for own record', function () {
    $user = ($this->userWithMedicalInfo)();

    $this->actingAs($user)
        ->post(route('allergies.store'), [
            'allergen' => 'Penicillin',
            'reaction' => 'Rash',
            'severity' => 'moderate',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('allergies', [
        'medical_information_id' => $user->medicalInformation->id,
        'allergen'               => 'Penicillin',
        'reaction'               => 'Rash',
        'severity'               => 'moderate',
    ]);
});

it('creating allergy requires medical information', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::User->value);

    $this->actingAs($user)
        ->post(route('allergies.store'), [
            'allergen' => 'Peanuts',
            'severity' => 'mild',
        ])
        ->assertNotFound();
});

it('create allergy validation errors', function () {
    $user = ($this->userWithMedicalInfo)();

    $this->actingAs($user)
        ->post(route('allergies.store'), [
            'allergen' => '',
            'severity' => 'extreme',
        ])
        ->assertSessionHasErrors(['allergen', 'severity']);
});

// --- Update ---

it('user can update own allergy', function () {
    $user    = ($this->userWithMedicalInfo)();
    $allergy = ($this->allergyFor)($user);

    $this->actingAs($user)
        ->patch(route('allergies.update', $allergy), [
            'allergen' => 'Tree nuts',
            'reaction' => 'Swelling',
            'severity' => 'life-threatening',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('allergies', [
        'id'       => $allergy->id,
        'allergen' => 'Tree nuts',
        'reaction' => 'Swelling',
        'severity' => 'life-threatening',
    ]);
});

it('user cannot update another users allergy', function () {
    $owner   = ($this->userWithMedicalInfo)();
    $other   = ($this->userWithMedicalInfo)();
    $allergy = ($this->allergyFor)($owner);

    $this->actingAs($other)
        ->patch(route('allergies.update', $allergy), [
            'allergen' => 'Hacked',
            'severity' => 'mild',
        ])
        ->assertNotFound();

    $this->assertDatabaseHas('allergies', ['id' => $allergy->id, 'allergen' => 'Peanuts']);
});

it('update allergy validation errors', function () {
    $user    = ($this->userWithMedicalInfo)();
    $allergy = ($this->allergyFor)($user);

    $this->actingAs($user)
        ->patch(route('allergies.update', $allergy), [
            'allergen' => 'Peanuts',
            'severity' => 'extreme',
        ])
        ->assertSessionHasErrors(['severity']);
});

// --- Delete ---

it('user can delete own allergy', function () {
    $user    = ($this->userWithMedicalInfo)();
    $allergy = ($this->allergyFor)($user);

    $this->actingAs($user)
        ->delete(route('allergies.destroy', $allergy))
        ->assertRedirect();

    $this->assertModelMissing($allergy);
});

it('user cannot delete another users allergy', function () {
    $owner   = ($this->userWithMedicalInfo)();
    $other   = ($this->userWithMedicalInfo)();
    $allergy = ($this->allergyFor)($owner);

    $this->actingAs($other)
        ->delete(route('allergies.destroy', $allergy))
        ->assertNotFound();

    $this->assertModelExists($allergy);
});

// --- Dashboard payload ---

it('dashboard payload includes allergies list', function () {
    $user = ($this->userWithMedicalInfo)();
    ($this->allergyFor)($user, ['allergen' => 'Peanuts']);
    ($this->allergyFor)($user, ['allergen' => 'Shellfish']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('medicalInfo.allergies', 2)
        );
});

it('dashboard cache reflects new allergy', function () {
    $user = ($this->userWithMedicalInfo)();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('medicalInfo.allergies', 0));

    $this->actingAs($user)->post(route('allergies.store'), [
        'allergen' => 'Latex',
        'severity' => 'mild',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('medicalInfo.allergies', 1));
});

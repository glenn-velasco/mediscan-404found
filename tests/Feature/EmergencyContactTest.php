<?php

use App\Enums\Gender;
use App\Enums\Role;
use App\Models\EmergencyContact;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

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

    $this->contactFor = function (User $user, array $overrides = []): EmergencyContact {
        return $user->medicalInformation->emergencyContacts()->create(array_merge([
            'name' => 'Jane Contact',
            'relationship' => 'Spouse',
            'is_primary' => false,
        ], $overrides));
    };
});

// --- Create ---

it('guests cannot create emergency contact', function () {
    $this->post(route('emergency-contacts.store'), [
        'name' => 'Jane Contact',
    ])->assertRedirect(route('login'));
});

it('user can create emergency contact for own record', function () {
    $user = ($this->userWithMedicalInfo)();

    $this->actingAs($user)
        ->post(route('emergency-contacts.store'), [
            'name' => 'Jane Contact',
            'relationship' => 'Spouse',
            'phone_country_code' => 'PH',
            'phone' => '9928727279',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('emergency_contacts', [
        'medical_information_id' => $user->medicalInformation->id,
        'name' => 'Jane Contact',
        'relationship' => 'Spouse',
    ]);
});

it('creating emergency contact requires medical information', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::User->value);

    $this->actingAs($user)
        ->post(route('emergency-contacts.store'), ['name' => 'Jane'])
        ->assertNotFound();
});

it('create emergency contact validation errors', function () {
    $user = ($this->userWithMedicalInfo)();

    $this->actingAs($user)
        ->post(route('emergency-contacts.store'), ['name' => ''])
        ->assertSessionHasErrors(['name']);
});

it('first emergency contact is automatically primary', function () {
    $user = ($this->userWithMedicalInfo)();

    $this->actingAs($user)
        ->post(route('emergency-contacts.store'), [
            'name' => 'Jane Contact',
            'is_primary' => false,
        ]);

    $this->assertDatabaseHas('emergency_contacts', [
        'medical_information_id' => $user->medicalInformation->id,
        'name' => 'Jane Contact',
        'is_primary' => true,
    ]);
});

it('creating a second contact as primary unmarks the first', function () {
    $user = ($this->userWithMedicalInfo)();
    $first = ($this->contactFor)($user, ['is_primary' => true]);

    $this->actingAs($user)
        ->post(route('emergency-contacts.store'), [
            'name' => 'Bob Contact',
            'is_primary' => true,
        ]);

    $this->assertDatabaseHas('emergency_contacts', ['id' => $first->id, 'is_primary' => false]);
    $this->assertDatabaseHas('emergency_contacts', ['name' => 'Bob Contact', 'is_primary' => true]);
});

it('creating a second contact without primary flag does not disturb existing primary', function () {
    $user = ($this->userWithMedicalInfo)();
    $first = ($this->contactFor)($user, ['is_primary' => true]);

    $this->actingAs($user)
        ->post(route('emergency-contacts.store'), ['name' => 'Bob Contact']);

    $this->assertDatabaseHas('emergency_contacts', ['id' => $first->id, 'is_primary' => true]);
    $this->assertDatabaseHas('emergency_contacts', ['name' => 'Bob Contact', 'is_primary' => false]);
});

// --- Update ---

it('user can update own emergency contact', function () {
    $user = ($this->userWithMedicalInfo)();
    $contact = ($this->contactFor)($user, ['is_primary' => true]);

    $this->actingAs($user)
        ->patch(route('emergency-contacts.update', $contact), [
            'name' => 'Updated Name',
            'relationship' => 'Parent',
            'is_primary' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('emergency_contacts', [
        'id' => $contact->id,
        'name' => 'Updated Name',
        'relationship' => 'Parent',
    ]);
});

it('user cannot update another users emergency contact', function () {
    $owner = ($this->userWithMedicalInfo)();
    $other = ($this->userWithMedicalInfo)();
    $contact = ($this->contactFor)($owner, ['is_primary' => true]);

    $this->actingAs($other)
        ->patch(route('emergency-contacts.update', $contact), [
            'name' => 'Hacked',
            'is_primary' => false,
        ])
        ->assertNotFound();

    $this->assertDatabaseHas('emergency_contacts', ['id' => $contact->id, 'name' => 'Jane Contact']);
});

it('update emergency contact validation errors', function () {
    $user = ($this->userWithMedicalInfo)();
    $contact = ($this->contactFor)($user, ['is_primary' => true]);

    $this->actingAs($user)
        ->patch(route('emergency-contacts.update', $contact), ['name' => ''])
        ->assertSessionHasErrors(['name']);
});

it('marking a different contact primary unmarks the previous one', function () {
    $user = ($this->userWithMedicalInfo)();
    $a = ($this->contactFor)($user, ['name' => 'A', 'is_primary' => true]);
    $b = ($this->contactFor)($user, ['name' => 'B', 'is_primary' => false]);

    $this->actingAs($user)
        ->patch(route('emergency-contacts.update', $b), [
            'name' => 'B',
            'is_primary' => true,
        ]);

    $this->assertDatabaseHas('emergency_contacts', ['id' => $a->id, 'is_primary' => false]);
    $this->assertDatabaseHas('emergency_contacts', ['id' => $b->id, 'is_primary' => true]);
});

it('patching is primary false on sole primary contact is allowed and leaves zero primary', function () {
    $user = ($this->userWithMedicalInfo)();
    $contact = ($this->contactFor)($user, ['is_primary' => true]);

    $this->actingAs($user)
        ->patch(route('emergency-contacts.update', $contact), [
            'name' => 'Jane Contact',
            'is_primary' => false,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('emergency_contacts', ['id' => $contact->id, 'is_primary' => false]);
});

// --- Delete ---

it('user can delete own emergency contact', function () {
    $user = ($this->userWithMedicalInfo)();
    $contact = ($this->contactFor)($user, ['is_primary' => true]);

    $this->actingAs($user)
        ->delete(route('emergency-contacts.destroy', $contact))
        ->assertRedirect();

    $this->assertModelMissing($contact);
});

it('user cannot delete another users emergency contact', function () {
    $owner = ($this->userWithMedicalInfo)();
    $other = ($this->userWithMedicalInfo)();
    $contact = ($this->contactFor)($owner, ['is_primary' => true]);

    $this->actingAs($other)
        ->delete(route('emergency-contacts.destroy', $contact))
        ->assertNotFound();

    $this->assertModelExists($contact);
});

it('deleting the primary contact leaves zero primary contacts', function () {
    $user = ($this->userWithMedicalInfo)();
    $primary = ($this->contactFor)($user, ['name' => 'Primary', 'is_primary' => true]);
    $other = ($this->contactFor)($user, ['name' => 'Other', 'is_primary' => false]);

    $this->actingAs($user)
        ->delete(route('emergency-contacts.destroy', $primary));

    $this->assertModelMissing($primary);
    $this->assertDatabaseHas('emergency_contacts', ['id' => $other->id, 'is_primary' => false]);
    $this->assertDatabaseMissing('emergency_contacts', [
        'medical_information_id' => $user->medicalInformation->id,
        'is_primary' => true,
    ]);
});

// --- Dashboard payload ---

it('dashboard payload includes emergency contacts list', function () {
    $user = ($this->userWithMedicalInfo)();
    ($this->contactFor)($user, ['name' => 'Contact A', 'is_primary' => true]);
    ($this->contactFor)($user, ['name' => 'Contact B', 'is_primary' => false]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('medicalInfo.emergency_contacts', 2)
        );
});

it('dashboard cache reflects new emergency contact', function () {
    $user = ($this->userWithMedicalInfo)();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('medicalInfo.emergency_contacts', 0));

    $this->actingAs($user)->post(route('emergency-contacts.store'), [
        'name' => 'Jane Contact',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('medicalInfo.emergency_contacts', 1));
});

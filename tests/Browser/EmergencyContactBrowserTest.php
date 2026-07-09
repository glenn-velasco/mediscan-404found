<?php

use App\Enums\Gender;
use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole(Role::User->value);
    $this->user->medicalInformation()->create([
        'first_name' => 'Ana',
        'last_name' => 'Reyes',
        'date_of_birth' => '1992-04-10',
        'gender' => Gender::Female,
        'no_blood_transfusion' => false,
    ]);

    $this->actingAs($this->user);
});

it('shows emergency contacts section on the dashboard', function () {
    visit(route('dashboard'))
        ->assertSee('Ana Reyes')
        ->assertNoJavascriptErrors();
});

it('adds an emergency contact from the dashboard', function () {
    visit(route('dashboard'))
        ->assertSee('Ana Reyes')
        ->pressAndWaitFor('Edit', 3)
        ->assertSee('Add contact')
        ->click('Add contact')
        ->type('name', 'Jane Doe')
        ->type('relationship', 'Spouse')
        ->pressAndWaitFor('@save-emergency-contact', 3)
        ->assertSee('Jane Doe')
        ->assertSee('Spouse')
        ->assertNoJavascriptErrors();

    $this->assertDatabaseHas('emergency_contacts', [
        'name' => 'Jane Doe',
        'relationship' => 'Spouse',
    ]);
});

it('deletes an emergency contact', function () {
    $this->user->medicalInformation->emergencyContacts()->create([
        'name' => 'Delete Me',
        'relationship' => 'Friend',
        'phone_country_code' => 'PH',
        'phone' => '9171234567',
        'is_primary' => false,
    ]);

    visit(route('dashboard'))
        ->assertSee('Delete Me')
        ->pressAndWaitFor('Edit', 3)
        ->assertSee('Delete Me')
        ->click('@delete-emergency-contact')
        ->pressAndWaitFor('Remove', 3)
        ->assertDontSee('Delete Me')
        ->assertNoJavascriptErrors();

    $this->assertDatabaseMissing('emergency_contacts', ['name' => 'Delete Me']);
});

it('edits an emergency contact', function () {
    $this->user->medicalInformation->emergencyContacts()->create([
        'name' => 'Original Name',
        'relationship' => 'Sibling',
        'phone_country_code' => 'PH',
        'phone' => '9171234567',
        'is_primary' => false,
    ]);

    visit(route('dashboard'))
        ->assertSee('Original Name')
        ->pressAndWaitFor('Edit', 3)
        ->assertSee('Original Name')
        ->click('@edit-emergency-contact')
        ->type('name', 'Updated Name')
        ->pressAndWaitFor('@save-emergency-contact', 3)
        ->assertSee('Updated Name')
        ->assertNoJavascriptErrors();

    $this->assertDatabaseHas('emergency_contacts', ['name' => 'Updated Name']);
    $this->assertDatabaseMissing('emergency_contacts', ['name' => 'Original Name']);
});

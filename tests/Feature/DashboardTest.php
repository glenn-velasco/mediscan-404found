<?php

use App\Enums\Gender;
use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->admin = function (): User {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        return $admin;
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

// --- Admin dashboard ---

it('guests are redirected from admin dashboard', function () {
    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));
});

it('admins can visit admin dashboard', function () {
    $this->actingAs(($this->admin)())
        ->get(route('admin.dashboard'))
        ->assertOk();
});

it('regular users cannot visit admin dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::User->value);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

// --- Patient dashboard ---

it('guests are redirected from patient dashboard', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

it('user can visit patient dashboard', function () {
    $this->actingAs(($this->userWithMedicalInfo)())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('dashboard'));
});

it('dashboard passes medical info when present', function () {
    $user = ($this->userWithMedicalInfo)();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->whereNot('medicalInfo', null)
        );
});

it('dashboard passes null medical info when absent', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::User->value);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('medicalInfo', null)
        );
});

it('deactivated user cannot visit dashboard', function () {
    $user = User::factory()->create(['deactivated_at' => now()]);
    $user->assignRole(Role::User->value);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

it('email change via dashboard shows back link on verification page', function () {
    $user = ($this->userWithMedicalInfo)();

    $this->actingAs($user)->patch(route('dashboard.update'), [
        'email' => 'changed@example.com',
        'first_name' => 'Ana',
        'last_name' => 'Reyes',
        'date_of_birth' => '1992-04-10',
        'gender' => Gender::Female->value,
    ])->assertSessionHasNoErrors();

    $this->assertSame('changed@example.com', $user->fresh()->email);

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('emailChangeBackUrl', route('dashboard'))
            ->where('emailChangeBackLabel', 'Back to dashboard')
        );
});

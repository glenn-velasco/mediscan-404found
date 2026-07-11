<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('shows correct seeded stats to an admin', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole(Role::Admin->value);

    $otherAdmin = User::factory()->create();
    $otherAdmin->assignRole(Role::Admin->value);

    $activeUsers = User::factory()->count(3)->create();
    foreach ($activeUsers as $user) {
        $user->assignRole(Role::User->value);
    }

    $deactivatedUser = User::factory()->create(['deactivated_at' => now()]);
    $deactivatedUser->assignRole(Role::User->value);

    $this->actingAs($viewer)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->where('stats.total', 6)
            ->where('stats.active', 5)
            ->where('stats.deactivated', 1)
            ->where('stats.by_role.admin', 2)
            ->where('stats.by_role.user', 4)
        );
});

it('forbids a non admin from viewing the dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::User->value);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

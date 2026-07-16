<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

it('returns dashboard component for admin', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->has('stats')
            ->has('stats.total')
            ->has('stats.active')
            ->has('stats.deactivated')
            ->has('stats.by_role')
        );
});

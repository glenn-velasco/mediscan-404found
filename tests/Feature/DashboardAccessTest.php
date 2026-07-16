<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

it('target admin can access dashboard', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $target = User::factory()->create();
    $target->assignRole(Role::User->value);
    $target->syncRoles([Role::Admin->value]);

    $this->actingAs($target->fresh())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->has('stats')
        );
});

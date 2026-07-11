<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

it('shows correct seeded stats on the 5 stat cards', function () {
    $this->seed(RoleAndPermissionSeeder::class);

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

    $this->actingAs($viewer);

    visit(route('admin.dashboard'))
        ->assertSee('Total Accounts')
        ->assertSee('Total Users')
        ->assertSee('Total Admins')
        ->assertSee('Active')
        ->assertSee('Deactivated')
        ->assertSee('6') // total
        ->assertSee('5') // active
        ->assertSee('1') // deactivated
        ->assertSee('4') // by_role.user
        ->assertSee('2') // by_role.admin
        ->assertNoJavascriptErrors();
});

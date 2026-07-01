<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

it('redirects guests from the dashboard', function () {
    visit(route('dashboard'))
        ->assertPathContains('login')
        ->assertNoJavascriptErrors();
});

it('shows the user the dashboard after login', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignRole(Role::User->value);
    $this->actingAs($user);

    visit(route('dashboard'))
        ->assertSee('No health record yet')
        ->assertNoJavascriptErrors();
});

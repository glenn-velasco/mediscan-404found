<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

it('redirects guests from the dashboard', function () {
    visit(route('dashboard'))
        ->assertUrlContains('login')
        ->assertNoJavascriptErrors();
});

it('shows the patient dashboard after login', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignRole(Role::User->value);
    $this->actingAs($user);

    visit(route('dashboard'))
        ->assertSee('Dashboard')
        ->assertNoJavascriptErrors();
});

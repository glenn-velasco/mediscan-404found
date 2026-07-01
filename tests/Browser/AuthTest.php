<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

it('shows the login page', function () {
    visit(route('login'))
        ->assertSee('Log in to your account')
        ->assertNoJavascriptErrors();
});

it('can log in as a patient', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignRole(Role::User->value);

    visit(route('login'))
        ->type('email', $user->email)
        ->type('password', 'password')
        ->press('Log in')
        ->assertSee('No health record yet')
        ->assertNoJavascriptErrors();
});

it('shows validation error for wrong password', function () {
    visit(route('login'))
        ->type('email', 'nobody@example.com')
        ->type('password', 'wrong')
        ->press('Log in')
        ->assertSee('credentials')
        ->assertNoJavascriptErrors();
});

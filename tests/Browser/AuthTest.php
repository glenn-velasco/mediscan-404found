<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

it('shows the login page', function () {
    visit(route('login'))
        ->assertSee('Log in to your account')
        ->assertNoJavascriptErrors();
});

it('does not show a sign up link on the login page', function () {
    visit(route('login'))
        ->assertDontSee('Sign up')
        ->assertNoJavascriptErrors();
});

it('the public registration page no longer exists', function () {
    $this->get('/register')->assertNotFound();
});

it('can log in as a patient', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignRole(Role::User->value);

    visit(route('login'))
        ->type('email', $user->email)
        ->type('password', 'password')
        ->press('Log in')
        ->assertSee('Professional Application')
        ->assertSee($user->fullname)
        ->assertNoJavascriptErrors();
});

it('deactivated user sees an error on the login page and stays logged out', function () {
    $user = User::factory()->create(['deactivated_at' => now()]);

    visit(route('login'))
        ->type('email', $user->email)
        ->type('password', 'password')
        ->press('Log in')
        ->assertPathIs('/login')
        ->assertSee('deactivated')
        ->assertNoJavascriptErrors();

    $this->assertGuest();
});

it('shows validation error for wrong password', function () {
    visit(route('login'))
        ->type('email', 'nobody@example.com')
        ->type('password', 'wrong')
        ->press('Log in')
        ->assertSee('credentials')
        ->assertNoJavascriptErrors();
});

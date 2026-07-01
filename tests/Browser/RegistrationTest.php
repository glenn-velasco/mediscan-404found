<?php

use Database\Seeders\RoleAndPermissionSeeder;

it('shows the registration page', function () {
    visit(route('register'))
        ->assertSee('Create your account')
        ->assertNoJavascriptErrors();
});

it('can register a new account', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    visit(route('register'))
        ->type('first_name', 'Juan')
        ->type('last_name', 'dela Cruz')
        ->type('email', 'juan@example.com')
        ->type('password', 'password')
        ->type('password_confirmation', 'password')
        ->press('Create account')
        ->assertNoJavascriptErrors();
});

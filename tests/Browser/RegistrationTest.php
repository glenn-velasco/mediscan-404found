<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

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
        ->type('date_of_birth', '1990-01-01')
        ->keys('gender', ['Enter'])
        ->keys('gender', ['Enter'])
        ->press('Create account')
        ->assertNoJavascriptErrors();

    $this->assertDatabaseHas('users', ['email' => 'juan@example.com']);
});

it('sends a verification email on registration', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    Notification::fake();

    visit(route('register'))
        ->type('first_name', 'Juan')
        ->type('last_name', 'dela Cruz')
        ->type('email', 'juan@example.com')
        ->type('password', 'password')
        ->type('password_confirmation', 'password')
        ->type('date_of_birth', '1990-01-01')
        ->keys('gender', ['Enter'])
        ->keys('gender', ['Enter'])
        ->press('Create account')
        ->assertNoJavascriptErrors();

    $user = User::where('email', 'juan@example.com')->firstOrFail();

    Notification::assertSentTo($user, VerifyEmail::class);
});

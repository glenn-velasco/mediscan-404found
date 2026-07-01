<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

it('sends a reset password notification when requested from the login flow', function () {
    Notification::fake();

    $user = User::factory()->create();

    visit(route('password.request'))
        ->type('email', $user->email)
        ->press('Email password reset link')
        ->assertNoJavascriptErrors();

    Notification::assertSentTo($user, ResetPassword::class);
});

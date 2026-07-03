<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('sends a password reset link via api', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->postJson('/api/v1/forgot-password', ['email' => $user->email])
        ->assertOk();

    Notification::assertSentTo($user, ResetPassword::class);
});

it('rejects password reset link requests for unknown emails', function () {
    $this->postJson('/api/v1/forgot-password', ['email' => 'unknown@example.com'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('resets the password with a valid token via api', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->postJson('/api/v1/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ])->assertOk();

    expect(Hash::check('new-secure-password', $user->fresh()->password))->toBeTrue();
});

it('revokes all api tokens when the password is reset', function () {
    $user = User::factory()->create();
    $user->createToken('phone');
    $user->createToken('tablet');
    $token = Password::createToken($user);

    $this->postJson('/api/v1/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ])->assertOk();

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('rejects password reset with an invalid token', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/reset-password', [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

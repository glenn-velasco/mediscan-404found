<?php

use App\Models\User;
use App\Notifications\Api\VerifyApiEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

it('resends the email verification notification via api', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/email/verification-notification')
        ->assertOk()
        ->assertJsonPath('message', 'Verification link sent.');

    Notification::assertSentTo($user, VerifyApiEmail::class);
});

it('does not resend verification to already verified users', function () {
    Notification::fake();
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/email/verification-notification')
        ->assertOk()
        ->assertJsonPath('message', 'Email already verified.');

    Notification::assertNothingSent();
});

it('users can change their email via api', function () {
    Notification::fake();
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->putJson('/api/v1/email', ['email' => 'new-email@example.com'])
        ->assertOk()
        ->assertJsonPath('data.email', 'new-email@example.com');

    $user->refresh();
    expect($user->email)->toBe('new-email@example.com');
    expect($user->email_verified_at)->toBeNull();
});

it('reports no changes when the email is unchanged', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->putJson('/api/v1/email', ['email' => $user->email])
        ->assertOk()
        ->assertJsonPath('message', 'No changes made.');
});

it('rejects an email already taken by another user', function () {
    $other = User::factory()->create();
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->putJson('/api/v1/email', ['email' => $other->email])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('users can change their password via api', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->putJson('/api/v1/password', [
        'current_password' => 'password',
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ])->assertOk();

    expect(Hash::check('new-secure-password', $user->fresh()->password))->toBeTrue();
});

it('rejects password change with a wrong current password', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->putJson('/api/v1/password', [
        'current_password' => 'wrong-password',
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
});

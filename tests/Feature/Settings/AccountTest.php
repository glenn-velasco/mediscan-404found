<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

it('account page is displayed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('account.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('settings/account'));
});

it('unverified user can still access account settings', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('account.edit'))
        ->assertOk();
});

it('user can update email', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);

    $this->actingAs($user)
        ->from(route('account.edit'))
        ->put(route('user-email.update'), ['email' => 'new@example.com'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('account.edit'));

    $user->refresh();

    $this->assertSame('new@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

it('no change when email is same', function () {
    $user = User::factory()->create(['email' => 'same@example.com']);

    $this->actingAs($user)
        ->from(route('account.edit'))
        ->put(route('user-email.update'), ['email' => 'same@example.com'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('account.edit'));

    $this->assertNotNull($user->refresh()->email_verified_at);
});

it('email update requires unique email', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create(['email' => 'mine@example.com']);

    $this->actingAs($user)
        ->from(route('account.edit'))
        ->put(route('user-email.update'), ['email' => 'taken@example.com'])
        ->assertSessionHasErrors('email')
        ->assertRedirect(route('account.edit'));

    $this->assertSame('mine@example.com', $user->refresh()->email);
});

it('email update is throttled', function () {
    RateLimiter::clear('login');

    $user = User::factory()->create();

    for ($i = 0; $i < 6; $i++) {
        $this->actingAs($user)->put(route('user-email.update'), [
            'email' => "attempt{$i}@example.com",
        ]);
    }

    $this->actingAs($user)
        ->put(route('user-email.update'), ['email' => 'one-too-many@example.com'])
        ->assertStatus(429);
});

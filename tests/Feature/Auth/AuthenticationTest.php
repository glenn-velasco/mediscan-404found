<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

it('login screen can be rendered', function () {
    $this->get(route('login'))->assertOk();
});

it('users can authenticate using the login screen', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignRole(Role::Admin->value);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('admin.dashboard', absolute: false));
});

it('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

it('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

it('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));
    $this->assertGuest();
});

it('deactivated user cannot login via the web login screen', function () {
    // Fortify's default authentication pipeline only checks credentials, so a
    // deactivated user's login form submission succeeds momentarily; it's the
    // CheckUserActive middleware on the very next protected-route request that
    // logs them back out. This mirrors the existing "kicked out on protected
    // routes" coverage, but drives it through the real login form instead of
    // actingAs(), matching the API-level rejection already covered for /api/v1/login.
    $user = User::factory()->create(['deactivated_at' => now()]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('professional-application.show'));

    $this->get(route('professional-application.show'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});

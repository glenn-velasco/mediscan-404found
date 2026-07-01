<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::emailVerification());
});

it('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('verification.notice'))->assertOk();
});

it('verification screen does not show back link for freshly unverified user', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertInertia(fn ($page) => $page->where('emailChangeBackUrl', null));
});

it('verification screen shows back link after email change', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('user-email.update'), ['email' => 'new@example.com']);

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertInertia(fn ($page) => $page
            ->where('emailChangeBackUrl', route('account.edit'))
            ->where('emailChangeBackLabel', 'Back to account settings')
        );
});

it('email can be verified', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);
    $this->assertTrue($user->fresh()->hasVerifiedEmail());
    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

it('email is not verified with invalid hash', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')],
    );

    $this->actingAs($user)->get($verificationUrl);

    Event::assertNotDispatched(Verified::class);
    $this->assertFalse($user->fresh()->hasVerifiedEmail());
});

it('email is not verified with invalid user id', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => 123, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($verificationUrl);

    Event::assertNotDispatched(Verified::class);
    $this->assertFalse($user->fresh()->hasVerifiedEmail());
});

it('verified user is redirected to dashboard from verification prompt', function () {
    $user = User::factory()->create();

    Event::fake();

    $response = $this->actingAs($user)->get(route('verification.notice'));

    Event::assertNotDispatched(Verified::class);
    $response->assertRedirect(route('home', absolute: false));
});

it('already verified user visiting verification link is redirected without firing event again', function () {
    $user = User::factory()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($verificationUrl)
        ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

    Event::assertNotDispatched(Verified::class);
    $this->assertTrue($user->fresh()->hasVerifiedEmail());
});

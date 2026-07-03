<?php

use App\Listeners\BroadcastEmailVerified;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Broadcasting\AnonymousEvent;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

function signedApiVerificationUrl(User $user): string
{
    return URL::temporarySignedRoute('email.verify', now()->addMinutes(60), [
        'id' => $user->getKey(),
        'hash' => sha1($user->getEmailForVerification()),
    ]);
}

it('renders the verification success page and verifies via a signed link without a session', function () {
    Event::fake();
    $user = User::factory()->unverified()->create();

    $this->get(signedApiVerificationUrl($user))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/verify-email')
            ->where('verified', true)
        );

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertDispatched(Verified::class);
    Event::assertListening(Verified::class, BroadcastEmailVerified::class);
});

it('rejects a tampered or expired signature', function () {
    $user = User::factory()->unverified()->create();
    $url = signedApiVerificationUrl($user).'-tampered';

    $this->get($url)->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects a hash that does not match the user email', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('email.verify', now()->addMinutes(60), [
        'id' => $user->getKey(),
        'hash' => sha1('someone-else@example.com'),
    ]);

    $this->get($url)->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('shows the success page without re-verifying an already verified user', function () {
    Event::fake();
    $user = User::factory()->create();

    $this->get(signedApiVerificationUrl($user))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/verify-email')
            ->where('verified', true)
        );

    Event::assertNotDispatched(Verified::class);
});

it('broadcasts EmailVerified on the user private channel', function () {
    $user = User::factory()->unverified()->create();

    $event = Mockery::mock(AnonymousEvent::class, ['App.Models.User.'.$user->id])->makePartial();
    $event->shouldReceive('send')->once();

    Broadcast::shouldReceive('private')
        ->once()
        ->with('App.Models.User.'.$user->id)
        ->andReturn($event);

    (new BroadcastEmailVerified)->handle(new Verified($user));

    expect($event->broadcastAs())->toBe('EmailVerified');
    expect($event->broadcastWith())->toHaveKey('email_verified_at');
});

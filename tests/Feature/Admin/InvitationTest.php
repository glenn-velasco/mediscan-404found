<?php

use App\Enums\Role;
use App\Models\Role as RoleModel;
use App\Models\User;
use App\Models\UserInvitation;
use App\Notifications\UserInvitationNotification;
use App\Services\Admin\DashboardService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Broadcasting\AnonymousEvent;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->admin = function (): User {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        return $admin;
    };

    $this->invitePayload = fn (array $overrides = []): array => array_merge([
        'email' => 'invite@example.com',
        'expires_in_days' => 3,
    ], $overrides);

    $this->acceptPayload = fn (array $overrides = []): array => array_merge([
        'first_name' => 'Juan',
        'middle_name' => null,
        'last_name' => 'dela Cruz',
        'suffix' => null,
        'dob' => '1990-01-15',
        'gender' => 'male',
        'address' => '123 Main St',
        'phone_number' => '+639171234567',
        'phone_country_code' => 'PH',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], $overrides);
});

it('admin can list invitations, showing the fullname of who invited them', function () {
    $admin = ($this->admin)();

    UserInvitation::create([
        'email' => 'listed@example.com',
        'token' => Str::random(64),
        'invited_by' => $admin->id,
        'expires_at' => now()->addDays(3),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.invitations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/invitations/index')
            ->where('invitations.data.0.invited_by', $admin->fullname)
        );
});

it('non admin cannot send invitation', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::User->value);

    $this->actingAs($user)
        ->post(route('admin.invitations.store'), ($this->invitePayload)())
        ->assertForbidden();
});

it('guest is redirected when sending invitation', function () {
    $this->post(route('admin.invitations.store'), ($this->invitePayload)())
        ->assertRedirect(route('login'));
});

it('admin can send invitation', function () {
    Notification::fake();

    $this->actingAs(($this->admin)())
        ->post(route('admin.invitations.store'), ($this->invitePayload)())
        ->assertRedirect(route('admin.invitations.index'));

    $this->assertDatabaseHas('user_invitations', ['email' => 'invite@example.com']);

    $invitation = UserInvitation::where('email', 'invite@example.com')->first();
    $this->assertSame(Role::Admin->value, $invitation->role->name);
});

it('invitations are always sent as admin regardless of any role field posted', function () {
    Notification::fake();

    $this->actingAs(($this->admin)())
        ->post(route('admin.invitations.store'), ($this->invitePayload)(['role' => Role::User->value]))
        ->assertRedirect(route('admin.invitations.index'));

    $invitation = UserInvitation::where('email', 'invite@example.com')->first();
    $this->assertSame(Role::Admin->value, $invitation->role->name);
});

it('invitation email is sent', function () {
    Notification::fake();

    $this->actingAs(($this->admin)())
        ->post(route('admin.invitations.store'), ($this->invitePayload)());

    Notification::assertSentOnDemand(
        UserInvitationNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'invite@example.com'
    );
});

it('broadcasts InvitationSent on the admin dashboard channel', function () {
    Notification::fake();

    $event = Mockery::mock(AnonymousEvent::class, ['admin-dashboard'])->makePartial();
    $event->shouldReceive('send')->once();

    Broadcast::shouldReceive('private')
        ->once()
        ->with('admin-dashboard')
        ->andReturn($event);

    $this->actingAs(($this->admin)())
        ->post(route('admin.invitations.store'), ($this->invitePayload)());

    expect($event->broadcastAs())->toBe('InvitationSent');
});

it('invitation expires in three days', function () {
    Notification::fake();

    $this->actingAs(($this->admin)())
        ->post(route('admin.invitations.store'), ($this->invitePayload)());

    $invitation = UserInvitation::where('email', 'invite@example.com')->first();

    $this->assertNotNull($invitation);
    $this->assertTrue($invitation->expires_at->isFuture());
    $this->assertTrue($invitation->expires_at->greaterThan(now()->addDays(2)));
});

it('invitation to existing user email is rejected', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $this->actingAs(($this->admin)())
        ->post(route('admin.invitations.store'), ($this->invitePayload)(['email' => 'existing@example.com']))
        ->assertSessionHasErrors('email');
});

it('duplicate pending invitation is rejected', function () {
    Notification::fake();

    $admin = ($this->admin)();

    $this->actingAs($admin)
        ->post(route('admin.invitations.store'), ($this->invitePayload)(['email' => 'dup@example.com']));

    $this->actingAs($admin)
        ->post(route('admin.invitations.store'), ($this->invitePayload)(['email' => 'dup@example.com']))
        ->assertSessionHasErrors('email');
});

it('re invitation is allowed after expiry', function () {
    Notification::fake();

    UserInvitation::create([
        'email' => 'expired@example.com',
        'token' => Str::random(64),
        'invited_by' => ($this->admin)()->id,
        'expires_at' => now()->subDay(),
    ]);

    $this->actingAs(($this->admin)())
        ->post(route('admin.invitations.store'), ($this->invitePayload)(['email' => 'expired@example.com']))
        ->assertRedirect(route('admin.invitations.index'))
        ->assertSessionHasNoErrors();
});

it('admin can resend invitation', function () {
    Notification::fake();

    $admin = ($this->admin)();

    $this->actingAs($admin)
        ->post(route('admin.invitations.store'), ($this->invitePayload)());

    $invitation = UserInvitation::where('email', 'invite@example.com')->first();

    $this->actingAs($admin)
        ->post(route('admin.invitations.resend', $invitation))
        ->assertRedirect();

    Notification::assertSentOnDemandTimes(UserInvitationNotification::class, 2);
});

// --- Accept invitation ---

it('guest can view accept invitation page', function () {
    $token = Str::random(64);

    UserInvitation::create([
        'email' => 'invited@example.com',
        'token' => $token,
        'invited_by' => ($this->admin)()->id,
        'expires_at' => now()->addDays(3),
    ]);

    $this->get(route('invitation.accept', $token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/accept-invitation')
            ->where('email', 'invited@example.com')
            ->where('token', $token)
        );
});

it('guest can accept invitation and create account', function () {
    $token = Str::random(64);

    UserInvitation::create([
        'email' => 'invited@example.com',
        'token' => $token,
        'invited_by' => ($this->admin)()->id,
        'expires_at' => now()->addDays(3),
    ]);

    $this->post(route('invitation.store', $token), ($this->acceptPayload)())
        ->assertRedirect(route('professional-application.show'));

    $this->assertDatabaseHas('users', ['email' => 'invited@example.com']);
    $this->assertAuthenticated();
});

it('broadcasts UserRegistered on the admin dashboard channel when an invitation is accepted', function () {
    $token = Str::random(64);

    UserInvitation::create([
        'email' => 'invited@example.com',
        'token' => $token,
        'invited_by' => ($this->admin)()->id,
        'expires_at' => now()->addDays(3),
    ]);

    $event = Mockery::mock(AnonymousEvent::class, ['admin-dashboard'])->makePartial();
    $event->shouldReceive('send')->once();

    Broadcast::shouldReceive('private')
        ->once()
        ->with('admin-dashboard')
        ->andReturn($event);

    $this->post(route('invitation.store', $token), ($this->acceptPayload)())
        ->assertRedirect(route('professional-application.show'));

    expect($event->broadcastAs())->toBe('UserRegistered');
    expect($event->broadcastWith())->toHaveKey('stats');
});

it('accepted user gets admin role', function () {
    $token = Str::random(64);

    UserInvitation::create([
        'email' => 'newuser@example.com',
        'role_id' => RoleModel::where('name', Role::Admin->value)->value('id'),
        'token' => $token,
        'invited_by' => ($this->admin)()->id,
        'expires_at' => now()->addDays(3),
    ]);

    $this->post(route('invitation.store', $token), ($this->acceptPayload)());

    $user = User::where('email', 'newuser@example.com')->first();
    $this->assertNotNull($user);
    $this->assertTrue($user->hasRole(Role::Admin->value));
});

it('invitation is marked accepted after use', function () {
    $token = Str::random(64);

    $invitation = UserInvitation::create([
        'email' => 'invited@example.com',
        'token' => $token,
        'invited_by' => ($this->admin)()->id,
        'expires_at' => now()->addDays(3),
    ]);

    $this->post(route('invitation.store', $token), ($this->acceptPayload)());

    $this->assertNotNull($invitation->fresh()->accepted_at);
});

it('invalid token redirects to login', function () {
    $this->get(route('invitation.accept', 'invalid-token'))
        ->assertRedirect(route('login'));
});

it('expired invitation redirects to login', function () {
    $token = Str::random(64);

    UserInvitation::create([
        'email' => 'expired@example.com',
        'token' => $token,
        'invited_by' => ($this->admin)()->id,
        'expires_at' => now()->subDay(),
    ]);

    $this->get(route('invitation.accept', $token))
        ->assertRedirect(route('login'));
});

it('already accepted invitation redirects to login', function () {
    $token = Str::random(64);

    UserInvitation::create([
        'email' => 'used@example.com',
        'token' => $token,
        'invited_by' => ($this->admin)()->id,
        'expires_at' => now()->addDays(3),
        'accepted_at' => now(),
    ]);

    $this->get(route('invitation.accept', $token))
        ->assertRedirect(route('login'));
});

it('cannot accept invitation with mismatched passwords', function () {
    $token = Str::random(64);

    UserInvitation::create([
        'email' => 'invited@example.com',
        'token' => $token,
        'invited_by' => ($this->admin)()->id,
        'expires_at' => now()->addDays(3),
    ]);

    $this->post(route('invitation.store', $token), ($this->acceptPayload)([
        'password' => 'password',
        'password_confirmation' => 'different',
    ]))->assertSessionHasErrors('password');

    $this->assertGuest();
});

it('cannot accept invitation with an invalid gender', function () {
    $token = Str::random(64);
    UserInvitation::create([
        'email' => 'invited@example.com',
        'token' => $token,
        'invited_by' => ($this->admin)()->id,
        'expires_at' => now()->addDays(3),
    ]);

    $this->post(route('invitation.store', $token), ($this->acceptPayload)(['gender' => 'other']))
        ->assertSessionHasErrors('gender');

    $this->assertGuest();
});

it('cannot accept invitation with a future dob', function () {
    $token = Str::random(64);
    UserInvitation::create([
        'email' => 'invited@example.com',
        'token' => $token,
        'invited_by' => ($this->admin)()->id,
        'expires_at' => now()->addDays(3),
    ]);

    $this->post(route('invitation.store', $token), ($this->acceptPayload)(['dob' => now()->addDay()->toDateString()]))
        ->assertSessionHasErrors('dob');

    $this->assertGuest();
});

it('cannot accept invitation with an oversized suffix or address', function () {
    $token = Str::random(64);
    UserInvitation::create([
        'email' => 'invited@example.com',
        'token' => $token,
        'invited_by' => ($this->admin)()->id,
        'expires_at' => now()->addDays(3),
    ]);

    $this->post(route('invitation.store', $token), ($this->acceptPayload)(['suffix' => str_repeat('a', 51)]))
        ->assertSessionHasErrors('suffix');

    $this->assertGuest();

    $this->post(route('invitation.store', $token), ($this->acceptPayload)(['address' => str_repeat('a', 1001)]))
        ->assertSessionHasErrors('address');

    $this->assertGuest();
});

it('cannot accept invitation with a malformed phone number', function () {
    $token = Str::random(64);
    UserInvitation::create([
        'email' => 'invited@example.com',
        'token' => $token,
        'invited_by' => ($this->admin)()->id,
        'expires_at' => now()->addDays(3),
    ]);

    $this->post(route('invitation.store', $token), ($this->acceptPayload)(['phone_number' => 'not-a-phone-number']))
        ->assertSessionHasErrors('phone_number');

    $this->assertGuest();
});

it('accepts invitation with every optional field filled and persists them', function () {
    $token = Str::random(64);
    UserInvitation::create([
        'email' => 'invited@example.com',
        'token' => $token,
        'invited_by' => ($this->admin)()->id,
        'expires_at' => now()->addDays(3),
    ]);

    $this->post(route('invitation.store', $token), ($this->acceptPayload)([
        'middle_name' => 'Santos',
        'suffix' => 'Jr.',
        'address' => '123 Main St',
        'phone_number' => '+639171234567',
    ]))->assertRedirect(route('professional-application.show'));

    $this->assertDatabaseHas('users', [
        'email' => 'invited@example.com',
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'dela Cruz',
        'suffix' => 'Jr.',
        'address' => '123 Main St',
        'phone_number' => '+639171234567',
        'phone_country_code' => 'PH',
    ]);
});

it('accepting an invitation flushes the admin dashboard stats cache', function () {
    // The BroadcastUserRegistered listener flushes the cache and then
    // immediately recomputes it (fresh) to build the broadcast payload, so
    // the key ends up repopulated rather than empty. The invariant to check
    // is that the stale sentinel is gone, replaced by real, current stats.
    Cache::put(DashboardService::STATS_CACHE_KEY, ['stale' => true], now()->addMonth());

    $token = Str::random(64);
    UserInvitation::create([
        'email' => 'invited@example.com',
        'token' => $token,
        'invited_by' => ($this->admin)()->id,
        'expires_at' => now()->addDays(3),
    ]);

    $this->post(route('invitation.store', $token), ($this->acceptPayload)());

    expect(Cache::get(DashboardService::STATS_CACHE_KEY))->not->toBe(['stale' => true])
        ->and(Cache::get(DashboardService::STATS_CACHE_KEY))->toHaveKey('total');
});

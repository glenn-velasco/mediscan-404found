<?php

use App\Enums\Role;
use App\Models\User;
use App\Models\UserInvitation;
use App\Notifications\UserInvitationNotification;
use Database\Seeders\RoleAndPermissionSeeder;
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
        'role' => Role::User->value,
        'expires_in_days' => 3,
    ], $overrides);

    $this->acceptPayload = fn (array $overrides = []): array => array_merge([
        'password' => 'password',
        'password_confirmation' => 'password',
        'first_name' => 'Juan',
        'last_name' => 'dela Cruz',
        'date_of_birth' => '1990-06-15',
        'gender' => 'male',
    ], $overrides);
});

it('admin can view invitation form', function () {
    $this->actingAs(($this->admin)())
        ->get(route('admin.invitations.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/invitations/create'));
});

it('non admin cannot view invitation form', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::User->value);

    $this->actingAs($user)
        ->get(route('admin.invitations.create'))
        ->assertForbidden();
});

it('guest is redirected from invitation form', function () {
    $this->get(route('admin.invitations.create'))
        ->assertRedirect(route('login'));
});

it('admin can send invitation', function () {
    Notification::fake();

    $this->actingAs(($this->admin)())
        ->post(route('admin.invitations.store'), ($this->invitePayload)())
        ->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseHas('user_invitations', ['email' => 'invite@example.com']);
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
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasNoErrors();
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
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('users', ['email' => 'invited@example.com']);
    $this->assertAuthenticated();
});

it('accepted user gets user role', function () {
    $token = Str::random(64);

    UserInvitation::create([
        'email' => 'newuser@example.com',
        'token' => $token,
        'invited_by' => ($this->admin)()->id,
        'expires_at' => now()->addDays(3),
    ]);

    $this->post(route('invitation.store', $token), ($this->acceptPayload)());

    $user = User::where('email', 'newuser@example.com')->first();
    $this->assertNotNull($user);
    $this->assertTrue($user->hasRole(Role::User->value));
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

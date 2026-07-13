<?php

use App\Enums\Role;
use App\Models\Role as RoleModel;
use App\Models\User;
use App\Models\UserInvitation;
use App\Notifications\UserInvitationNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

it('invite dialog has no role select, only email and expiry', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);
    $this->actingAs($admin);

    visit(route('admin.invitations.index'))
        ->press('Invite user')
        ->assertSee('Invite a user')
        ->assertSee('Email address')
        ->assertDontSee('Role')
        ->assertNoJavascriptErrors();
});

it('admin can send an invite, which is always sent as admin', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);
    $this->actingAs($admin);

    visit(route('admin.invitations.index'))
        ->press('Invite user')
        ->type('invite-email', 'new-admin@example.com')
        ->press('Send invitation')
        ->assertNoJavascriptErrors();

    $invitation = UserInvitation::where('email', 'new-admin@example.com')->firstOrFail();
    $this->assertSame(Role::Admin->value, $invitation->role->name);
});

it('guest can accept an admin invitation and create an account', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $inviter = User::factory()->create();
    $inviter->assignRole(Role::Admin->value);

    $invitation = UserInvitation::create([
        'email' => 'new-admin@example.com',
        'role_id' => RoleModel::where('name', Role::Admin->value)->value('id'),
        'token' => Str::random(64),
        'invited_by' => $inviter->id,
        'expires_at' => now()->addDays(3),
    ]);

    $page = visit(route('invitation.accept', $invitation->token))
        ->type('first_name', 'New')
        ->type('middle_name', 'Middleton')
        ->type('last_name', 'Admin')
        ->type('suffix', 'Jr.')
        ->type('dob', '1990-01-15');

    selectRadixOption($page, '#gender', 'Male');

    $page->type('street', '123 Main St')
        ->type('unit', 'Apt 4B')
        ->type('city', 'Manila')
        ->type('province', 'Metro Manila')
        ->type('postal_code', '1000');

    selectRadixOption($page, '#country', 'Philippines');
    selectRadixOption($page, '#registration_country', 'Philippines (+63)');
    $page->type('registration_phone', '9171234567');

    $page->type('password', 'password')
        ->type('password_confirmation', 'password')
        ->press('Create account')
        ->assertNoJavascriptErrors();

    $newUser = User::where('email', 'new-admin@example.com')->firstOrFail();
    expect($newUser->hasRole(Role::Admin->value))->toBeTrue()
        ->and($newUser->middle_name)->toBe('Middleton')
        ->and($newUser->suffix)->toBe('Jr.')
        ->and($newUser->address)->toBe('123 Main St, Apt 4B, Manila, Metro Manila, 1000, PH');
});

it('gender select only offers Male and Female', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $inviter = User::factory()->create();
    $inviter->assignRole(Role::Admin->value);

    $invitation = UserInvitation::create([
        'email' => 'gender-check@example.com',
        'token' => Str::random(64),
        'invited_by' => $inviter->id,
        'expires_at' => now()->addDays(3),
    ]);

    visit(route('invitation.accept', $invitation->token))
        ->click('#gender')
        ->assertSee('Male')
        ->assertSee('Female')
        ->assertDontSee('Other')
        ->assertDontSee('Non-binary')
        ->assertNoJavascriptErrors();
});

it('shows a validation error and does not create an account when required fields are blank', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $inviter = User::factory()->create();
    $inviter->assignRole(Role::Admin->value);

    $invitation = UserInvitation::create([
        'email' => 'blank-fields@example.com',
        'token' => Str::random(64),
        'invited_by' => $inviter->id,
        'expires_at' => now()->addDays(3),
    ]);

    visit(route('invitation.accept', $invitation->token))
        ->press('Create account')
        ->assertPathIs('/invite/'.$invitation->token)
        ->assertNoJavascriptErrors();

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'blank-fields@example.com']);
});

it('rejects a future dob through the accept invitation form', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $inviter = User::factory()->create();
    $inviter->assignRole(Role::Admin->value);

    $invitation = UserInvitation::create([
        'email' => 'future-dob@example.com',
        'token' => Str::random(64),
        'invited_by' => $inviter->id,
        'expires_at' => now()->addDays(3),
    ]);

    $page = visit(route('invitation.accept', $invitation->token))
        ->type('first_name', 'New')
        ->type('last_name', 'Admin')
        ->type('street', '123 St')
        ->type('unit', 'Unit 1')
        ->type('city', 'City')
        ->type('province', 'Province')
        ->type('postal_code', '1000')
        ->type('dob', now()->addDay()->toDateString());

    selectRadixOption($page, '#gender', 'Male');
    selectRadixOption($page, '#country', 'Philippines');

    $page->type('password', 'password')
        ->type('password_confirmation', 'password')
        ->press('Create account')
        ->assertPathIs('/invite/'.$invitation->token)
        ->assertNoJavascriptErrors();

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'future-dob@example.com']);
});

it('rejects an oversized suffix and a malformed phone number through the accept invitation form', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $inviter = User::factory()->create();
    $inviter->assignRole(Role::Admin->value);

    $invitation = UserInvitation::create([
        'email' => 'oversized-fields@example.com',
        'token' => Str::random(64),
        'invited_by' => $inviter->id,
        'expires_at' => now()->addDays(3),
    ]);

    $page = visit(route('invitation.accept', $invitation->token))
        ->type('first_name', 'New')
        ->type('last_name', 'Admin')
        ->type('street', '123 St')
        ->type('unit', 'Unit 1')
        ->type('city', 'City')
        ->type('province', 'Province')
        ->type('postal_code', '1000')
        ->type('suffix', str_repeat('a', 51))
        ->type('dob', '1990-01-15');

    selectRadixOption($page, '#gender', 'Male');
    selectRadixOption($page, '#country', 'Philippines');

    $page->type('registration_phone', 'not-a-phone-number')
        ->type('password', 'password')
        ->type('password_confirmation', 'password')
        ->press('Create account')
        ->assertPathIs('/invite/'.$invitation->token)
        ->assertNoJavascriptErrors();

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'oversized-fields@example.com']);
});

it('admin can resend a pending invitation', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);
    $this->actingAs($admin);

    $invitation = UserInvitation::create([
        'email' => 'resend-me@example.com',
        'role_id' => RoleModel::where('name', Role::Admin->value)->value('id'),
        'token' => Str::random(64),
        'invited_by' => $admin->id,
        'expires_at' => now()->addDays(3),
    ]);

    visit(route('admin.invitations.index'))
        ->assertSee('resend-me@example.com')
        ->click('.lucide-send-horizontal')
        ->assertNoJavascriptErrors();

    Notification::assertSentOnDemandTimes(UserInvitationNotification::class, 1);
});

it('admin can revoke an invitation via the confirm dialog', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);
    $this->actingAs($admin);

    $invitation = UserInvitation::create([
        'email' => 'revoke-me@example.com',
        'token' => Str::random(64),
        'invited_by' => $admin->id,
        'expires_at' => now()->addDays(3),
    ]);

    visit(route('admin.invitations.index'))
        ->assertSee('revoke-me@example.com')
        ->click('.lucide-trash2')
        ->assertSee('Delete invitation?')
        ->press('Delete')
        ->assertDontSee('revoke-me@example.com')
        ->assertNoJavascriptErrors();

    $this->assertDatabaseMissing('user_invitations', ['id' => $invitation->id]);
});

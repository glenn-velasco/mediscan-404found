<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Broadcast;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

// --- App.Models.User.{id} channel ---

it('authorizes the App.Models.User channel only for the matching user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $nonexistentId = $otherUser->id;
    $otherUser->delete();

    $callback = Broadcast::getChannels()->get('App.Models.User.{id}');

    expect($callback($user, (string) $user->id))->toBeTrue();
    expect($callback($user, (string) $nonexistentId))->toBeFalse();

    $anotherRealUser = User::factory()->create();
    expect($callback($user, (string) $anotherRealUser->id))->toBeFalse();
});

// --- admin-dashboard channel ---

it('authorizes the admin-dashboard channel only for admins', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    $user = User::factory()->create();
    $user->assignRole(Role::User->value);

    $callback = Broadcast::getChannels()->get('admin-dashboard');

    expect($callback($admin))->toBeTrue();
    expect($callback($user))->toBeFalse();
});

it('authorizes the family channel for any authenticated user and returns their data', function () {
    $user = User::factory()->create();

    $callback = Broadcast::getChannels()->get('family');

    expect($callback($user))->toBe([
        'id' => $user->id,
        'name' => $user->fullname,
        'email' => $user->email,
    ]);
});

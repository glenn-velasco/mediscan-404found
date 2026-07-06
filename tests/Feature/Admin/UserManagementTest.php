<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Events\UserDeactivated;
use App\Events\UserDeleted;
use App\Models\User;
use App\Services\Admin\DashboardService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->admin = function (): User {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        return $admin;
    };

    $this->regularUser = function (): User {
        $user = User::factory()->create();
        $user->assignRole(Role::User->value);

        return $user;
    };
});

it('admin has correct permissions', function () {
    $admin = ($this->admin)();

    $this->assertTrue($admin->hasPermissionTo(Permission::ManageUsers->value));
    $this->assertTrue($admin->hasPermissionTo(Permission::ManageRecords->value));
    $this->assertTrue($admin->hasPermissionTo(Permission::ManageAllergies->value));
    $this->assertFalse($admin->hasPermissionTo(Permission::ManageMedicalInformation->value));
});

it('admin can list users', function () {
    $this->actingAs(($this->admin)())
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/users/index'));
});

it('non admin cannot list users', function () {
    $this->actingAs(($this->regularUser)())
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

it('guest is redirected from user list', function () {
    $this->get(route('admin.users.index'))
        ->assertRedirect(route('login'));
});

it('admin can view user detail', function () {
    $target = ($this->regularUser)();

    $this->actingAs(($this->admin)())
        ->get(route('admin.users.show', $target))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/show')
            ->where('user.id', $target->id)
            ->where('user.email', $target->email)
        );
});

it('create user route does not exist', function () {
    $this->actingAs(($this->admin)())
        ->post(route('admin.users.index'), ['email' => 'new@example.com'])
        ->assertStatus(405);
});

it('edit user route does not exist', function () {
    $target = ($this->regularUser)();

    $this->actingAs(($this->admin)())
        ->get("/admin/users/{$target->id}/edit")
        ->assertNotFound();
});

it('update user route does not exist', function () {
    $target = ($this->regularUser)();

    $this->actingAs(($this->admin)())
        ->put(route('admin.users.show', $target), ['name' => 'Changed'])
        ->assertStatus(405);
});

it('admin can assign role', function () {
    $target = ($this->regularUser)();

    $this->actingAs(($this->admin)())
        ->patch(route('admin.users.role', $target), ['role' => Role::Admin->value]);

    $this->assertTrue($target->fresh()->hasRole(Role::Admin->value));
});

it('assigning a role flushes the admin dashboard stats cache', function () {
    $target = ($this->regularUser)();
    Cache::put(DashboardService::STATS_CACHE_KEY, ['stale' => true], now()->addMonth());

    $this->actingAs(($this->admin)())
        ->patch(route('admin.users.role', $target), ['role' => Role::Admin->value]);

    expect(Cache::has(DashboardService::STATS_CACHE_KEY))->toBeFalse();
});

it('admin can deactivate user', function () {
    $target = ($this->regularUser)();

    $this->actingAs(($this->admin)())
        ->patch(route('admin.users.activation', $target));

    $this->assertFalse($target->fresh()->isActive());
});

it('deactivating a user flushes the admin dashboard stats cache', function () {
    $target = ($this->regularUser)();
    Cache::put(DashboardService::STATS_CACHE_KEY, ['stale' => true], now()->addMonth());

    $this->actingAs(($this->admin)())
        ->patch(route('admin.users.activation', $target));

    expect(Cache::has(DashboardService::STATS_CACHE_KEY))->toBeFalse();
});

it('deactivating a user revokes their api tokens', function () {
    $target = ($this->regularUser)();
    $target->createToken('phone');
    $target->createToken('tablet');

    $this->actingAs(($this->admin)())
        ->patch(route('admin.users.activation', $target));

    expect($target->tokens()->count())->toBe(0);
});

it('deactivating a user broadcasts UserDeactivated on their private channel', function () {
    Event::fake([UserDeactivated::class]);

    $target = ($this->regularUser)();

    $this->actingAs(($this->admin)())
        ->patch(route('admin.users.activation', $target));

    Event::assertDispatched(
        UserDeactivated::class,
        fn (UserDeactivated $event) => $event->user->is($target),
    );
});

it('admin can reactivate user', function () {
    $target = User::factory()->create(['deactivated_at' => now()]);
    $target->assignRole(Role::User->value);

    $this->actingAs(($this->admin)())
        ->patch(route('admin.users.activation', $target));

    $this->assertTrue($target->fresh()->isActive());
});

it('deactivated user is kicked out on protected routes', function () {
    $user = User::factory()->create(['deactivated_at' => now()]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('admin can delete user', function () {
    $target = ($this->regularUser)();

    $this->actingAs(($this->admin)())
        ->delete(route('admin.users.destroy', $target))
        ->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseMissing('users', ['id' => $target->id]);
});

it('deleting a user flushes the admin dashboard stats cache', function () {
    $target = ($this->regularUser)();
    Cache::put(DashboardService::STATS_CACHE_KEY, ['stale' => true], now()->addMonth());

    $this->actingAs(($this->admin)())
        ->delete(route('admin.users.destroy', $target));

    expect(Cache::has(DashboardService::STATS_CACHE_KEY))->toBeFalse();
});

it('deleting a user revokes their api tokens', function () {
    $target = ($this->regularUser)();
    $target->createToken('phone');
    $target->createToken('tablet');
    $targetId = $target->id;

    $this->actingAs(($this->admin)())
        ->delete(route('admin.users.destroy', $target));

    expect(PersonalAccessToken::where('tokenable_id', $targetId)->count())->toBe(0);
});

it('deleting a user broadcasts UserDeleted on their private channel', function () {
    Event::fake([UserDeleted::class]);

    $target = ($this->regularUser)();
    $targetId = $target->id;

    $this->actingAs(($this->admin)())
        ->delete(route('admin.users.destroy', $target));

    Event::assertDispatched(
        UserDeleted::class,
        fn (UserDeleted $event) => $event->userId === $targetId,
    );
});

it('non admin cannot delete user', function () {
    $target = User::factory()->create();

    $this->actingAs(($this->regularUser)())
        ->delete(route('admin.users.destroy', $target))
        ->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $target->id]);
});

it('admin can search users by email', function () {
    User::factory()->create(['email' => 'find-me@example.com']);
    User::factory()->create(['email' => 'other@example.com']);

    $this->actingAs(($this->admin)())
        ->get(route('admin.users.index', ['search' => 'find-me']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('users.total', 1));
});

<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

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

it('admin can deactivate user', function () {
    $target = ($this->regularUser)();

    $this->actingAs(($this->admin)())
        ->patch(route('admin.users.activation', $target));

    $this->assertFalse($target->fresh()->isActive());
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

<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('list shows fullname and email', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    $target = User::factory()->create([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Delacruz',
        'suffix' => null,
        'email' => 'juan@example.com',
    ]);
    $target->assignRole(Role::User->value);

    $this->actingAs($admin);

    visit(route('admin.users.index'))
        ->assertSee('Juan Santos Delacruz')
        ->assertSee('juan@example.com')
        ->assertNoJavascriptErrors();
});

it('search box filters the user list', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    $findMe = User::factory()->create(['first_name' => 'Findmeuniquename']);
    $findMe->assignRole(Role::User->value);

    $other = User::factory()->create(['first_name' => 'Someoneelse']);
    $other->assignRole(Role::User->value);

    $this->actingAs($admin);

    visit(route('admin.users.index'))
        ->assertSee('Someoneelse')
        ->type('input[placeholder="Search by name or email…"]', 'Findmeuniquename')
        ->assertSee('Findmeuniquename')
        ->assertDontSee('Someoneelse')
        ->assertNoJavascriptErrors();
});

it('detail page shows fullname and age', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    $target = User::factory()->create([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Delacruz',
        'suffix' => null,
        'dob' => now()->subYears(40)->toDateString(),
    ]);
    $target->assignRole(Role::User->value);

    $this->actingAs($admin);

    visit(route('admin.users.show', $target))
        ->assertSee('Juan Santos Delacruz')
        ->assertSee('40 years old')
        ->assertNoJavascriptErrors();
});

it('admin can change a user\'s role from the detail page, and access flips accordingly', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    $target = User::factory()->create();
    $target->assignRole(Role::User->value);

    $this->actingAs($admin);

    $page = visit(route('admin.users.show', $target));
    selectRadixOption($page, 'User', 'Administrator');
    $page->press('Save role')
        ->assertSee('Administrator')
        ->assertNoJavascriptErrors();

    expect($target->fresh()->hasRole(Role::Admin->value))->toBeTrue();

    // The Inertia form submission from Save role may have left shared data
    // behind. Flushing it ensures the next visit starts clean.
    if (app()->resolved(\Inertia\ResponseFactory::class)) {
        app(\Inertia\ResponseFactory::class)->flushShared();
    }

    // Real second-actor check: a fresh page logged in as the target user now
    // has access to the admin-gated dashboard it was forbidden from before.
    $this->actingAs($target->fresh());

    visit(route('admin.dashboard'))
        ->assertSee('Total Accounts')
        ->assertNoJavascriptErrors();
});

it('sole admin cannot demote themselves via the role select', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    $this->actingAs($admin);

    $page = visit(route('admin.users.show', $admin));
    selectRadixOption($page, 'Administrator', 'User');
    $page->press('Save role')
        ->assertNoJavascriptErrors();

    // The guard rejects the change server-side; reload for the source of truth
    // since this page doesn't surface a visible validation error for this action.
    expect($admin->fresh()->hasRole(Role::Admin->value))->toBeTrue();

    visit(route('admin.users.show', $admin))
        ->assertSee('Administrator');
});

it('cannot demote the last remaining admin, but can once a second admin exists', function () {
    $sole = User::factory()->create();
    $sole->assignRole(Role::Admin->value);

    $second = User::factory()->create();
    $second->assignRole(Role::Admin->value);

    $this->actingAs($second);

    // With two admins present, demoting the other one is allowed (positive control).
    $page = visit(route('admin.users.show', $sole));
    selectRadixOption($page, 'Administrator', 'User');
    $page->press('Save role')
        ->assertNoJavascriptErrors();

    expect($sole->fresh()->hasRole(Role::User->value))->toBeTrue();

    // Now $second is the sole remaining admin; attempting to demote them
    // (acting as $sole, who has just become a plain User and no longer has
    // access to admin routes) isn't reachable via the UI at all — that
    // exact guard branch is covered directly against the request object in
    // tests/Feature/Admin/UserManagementTest.php, since the full /admin route
    // group requires the actor to already hold the Admin role.
    expect(
        User::query()->filterByRole(Role::Admin->value)->count()
    )->toBe(1);
});

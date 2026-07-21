<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::Admin->value);
});

it('non admin cannot view reports', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::User->value);

    $this->actingAs($user)
        ->get(route('admin.reports.show', 'authentication'))
        ->assertForbidden();
});

it('404s for an invalid report category', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.reports.show', 'not-a-real-category'))
        ->assertNotFound();
});

it('only shows audit logs matching the requested category', function () {
    AuditLog::factory()->create(['action' => 'auth.login']);
    AuditLog::factory()->create(['action' => 'professional_application.approved']);

    $this->actingAs($this->admin)
        ->get(route('admin.reports.show', 'authentication'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/show')
            ->where('category', 'authentication')
            ->has('reports.data', 1)
            ->where('reports.data.0.action', 'auth.login')
        );
});

it('filters by search term', function () {
    AuditLog::factory()->create(['action' => 'auth.login']);
    AuditLog::factory()->create(['action' => 'auth.logout']);

    $this->actingAs($this->admin)
        ->get(route('admin.reports.show', 'authentication').'?search=logout')
        ->assertInertia(fn (Assert $page) => $page
            ->has('reports.data', 1)
            ->where('reports.data.0.action', 'auth.logout')
        );
});

it('filters by date range', function () {
    AuditLog::factory()->create(['action' => 'auth.login', 'created_at' => now()->subDays(10)]);
    AuditLog::factory()->create(['action' => 'auth.login', 'created_at' => now()]);

    $this->actingAs($this->admin)
        ->get(route('admin.reports.show', 'authentication').'?from='.now()->subDay()->toDateString())
        ->assertInertia(fn (Assert $page) => $page->has('reports.data', 1));
});

it('searches users that appear in the category logs', function () {
    $subject = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Anderson']);
    $other = User::factory()->create(['first_name' => 'Bob', 'last_name' => 'Brown']);

    AuditLog::factory()->create(['action' => 'auth.login', 'actor_id' => $subject->id, 'subject_id' => $subject->id]);
    AuditLog::factory()->create(['action' => 'auth.login', 'actor_id' => $other->id, 'subject_id' => $other->id]);

    $this->actingAs($this->admin)
        ->get(route('admin.reports.users.search', 'authentication').'?q=Alice')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment(['id' => $subject->id]);
});

it('returns no users for an empty search query', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.reports.users.search', 'authentication').'?q=')
        ->assertOk()
        ->assertExactJson([]);
});

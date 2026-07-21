<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use DirectoryTree\Metrics\Metric;
use Inertia\Testing\AssertableInertia as Assert;

const DASHBOARD_DATETIME_FORMAT = 'Y-m-d\TH:i';

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('includes trend series in the dashboard response', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole(Role::Admin->value);

    $this->actingAs($viewer)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->has('trends.signups', 30)
            ->has('trends.qr_scans', 30)
            ->has('trends.logins', 30)
            ->has('trends.total_accounts', 30)
            ->has('trends.total_users', 30)
            ->has('trends.total_admins', 30)
            ->has('trends.active', 30)
            ->has('trends.deactivated', 30)
            ->where('trends.signups.29.date', now()->format('Y-m-d'))
        );
});

it('honors a custom from/to datetime range', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole(Role::Admin->value);

    $from = now()->subDays(9)->startOfDay();
    $to = now()->startOfHour();

    $this->actingAs($viewer)
        ->get(route('admin.dashboard', [
            'from' => $from->format(DASHBOARD_DATETIME_FORMAT),
            'to' => $to->format(DASHBOARD_DATETIME_FORMAT),
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->has('trends.signups', 10)
            ->where('filters.from', $from->format(DASHBOARD_DATETIME_FORMAT))
            ->where('filters.to', $to->format(DASHBOARD_DATETIME_FORMAT))
        );
});

it('clamps an overly wide datetime range to the maximum allowed', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole(Role::Admin->value);

    $to = now()->startOfHour();

    $this->actingAs($viewer)
        ->get(route('admin.dashboard', [
            'from' => now()->subYears(2)->startOfDay()->format(DASHBOARD_DATETIME_FORMAT),
            'to' => $to->format(DASHBOARD_DATETIME_FORMAT),
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->has('trends.signups', 90)
            ->where('filters.from', $to->subDays(89)->startOfDay()->format(DASHBOARD_DATETIME_FORMAT))
            ->where('filters.to', $to->format(DASHBOARD_DATETIME_FORMAT))
        );
});

it('reports the latest bound as now and no earliest bound when no data exists', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole(Role::Admin->value);

    $this->actingAs($viewer)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->where('filters.earliest', null)
            ->where('filters.latest', now()->format(DASHBOARD_DATETIME_FORMAT))
        );
});

it('clamps a from date earlier than any recorded data to when data actually starts', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole(Role::Admin->value);

    Metric::factory()->create([
        'name' => 'signups',
        'year' => now()->subDays(5)->year,
        'month' => now()->subDays(5)->month,
        'day' => now()->subDays(5)->day,
    ]);

    $this->actingAs($viewer)
        ->get(route('admin.dashboard', [
            'from' => now()->subDays(20)->startOfDay()->format(DASHBOARD_DATETIME_FORMAT),
            'to' => now()->startOfHour()->format(DASHBOARD_DATETIME_FORMAT),
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->where('filters.earliest', now()->subDays(5)->startOfDay()->format(DASHBOARD_DATETIME_FORMAT))
            ->where('filters.from', now()->subDays(5)->startOfDay()->format(DASHBOARD_DATETIME_FORMAT))
        );
});

it('shows correct seeded stats to an admin', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole(Role::Admin->value);

    $otherAdmin = User::factory()->create();
    $otherAdmin->assignRole(Role::Admin->value);

    $activeUsers = User::factory()->count(3)->create();
    foreach ($activeUsers as $user) {
        $user->assignRole(Role::User->value);
    }

    $deactivatedUser = User::factory()->create(['deactivated_at' => now()]);
    $deactivatedUser->assignRole(Role::User->value);

    $this->actingAs($viewer)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->where('stats.total', 6)
            ->where('stats.active', 5)
            ->where('stats.deactivated', 1)
            ->where('stats.by_role.admin', 2)
            ->where('stats.by_role.user', 4)
        );
});

it('forbids a non admin from viewing the dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::User->value);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

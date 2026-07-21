<?php

use App\Enums\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleAndPermissionSeeder;
use DirectoryTree\Metrics\Metric;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('snapshots today\'s user counts', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    $users = User::factory()->count(2)->create();
    foreach ($users as $user) {
        $user->assignRole(Role::User->value);
    }

    $deactivated = User::factory()->create(['deactivated_at' => now()]);
    $deactivated->assignRole(Role::User->value);

    $this->artisan('metrics:snapshot-users')->assertSuccessful();

    $today = now()->format('Y-m-d');

    expect(valueOf('users:total', $today))->toBe(4)
        ->and(valueOf('users:active', $today))->toBe(3)
        ->and(valueOf('users:deactivated:total', $today))->toBe(1)
        ->and(valueOf('users:role:admin', $today))->toBe(1)
        ->and(valueOf('users:role:user', $today))->toBe(3);
});

it('backfills previous days using historical created_at cutoffs', function () {
    User::factory()->create(['created_at' => now()->subDays(5)])
        ->assignRole(Role::User->value);

    User::factory()->create(['created_at' => now()->subDays(1)])
        ->assignRole(Role::User->value);

    $this->artisan('metrics:snapshot-users', ['--backfill-days' => 3])->assertSuccessful();

    expect(valueOf('users:total', now()->subDays(3)->format('Y-m-d')))->toBe(1)
        ->and(valueOf('users:total', now()->subDays(1)->format('Y-m-d')))->toBe(2)
        ->and(valueOf('users:total', now()->format('Y-m-d')))->toBe(2);
});

function valueOf(string $name, string $date): int
{
    $day = CarbonImmutable::parse($date);

    return (int) Metric::query()
        ->where('name', $name)
        ->where('year', $day->year)
        ->where('month', $day->month)
        ->where('day', $day->day)
        ->sum('value');
}

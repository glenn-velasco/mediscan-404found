<?php

use App\Enums\Role;
use App\Models\User;
use App\Services\User\UserService;
use Database\Seeders\RoleAndPermissionSeeder;

it('reflects a newly assigned role immediately under a persistent cache store', function () {
    // The test suite forces CACHE_STORE=array (phpunit.xml), which doesn't
    // persist across requests anyway — that could mask a real staleness bug
    // in Spatie's 24h permission/role cache, since app/ never calls
    // forgetCachedPermissions() manually and relies entirely on the package's
    // own auto-flush-on-write. Force the real, persistent store this app
    // actually uses (config/cache.php defaults CACHE_STORE to "database") so
    // this test would actually fail if that auto-flush stopped working.
    config(['cache.default' => 'database']);

    $this->seed(RoleAndPermissionSeeder::class);

    $target = User::factory()->create();
    $target->assignRole(Role::User->value);

    app(UserService::class)->assignRole($target, Role::Admin);

    expect($target->fresh()->hasRole(Role::Admin->value))->toBeTrue()
        ->and($target->fresh()->hasRole(Role::User->value))->toBeFalse();

    $this->actingAs($target->fresh())
        ->get(route('admin.dashboard'))
        ->assertOk();
});

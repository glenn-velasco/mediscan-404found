<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use DirectoryTree\Metrics\Facades\Metrics;
use DirectoryTree\Metrics\Measurable;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Metrics::fake();

    $this->validPayload = function (array $overrides = []): array {
        return array_merge([
            'first_name' => 'Juan',
            'middle_name' => null,
            'last_name' => 'dela Cruz',
            'suffix' => null,
            'dob' => '1990-01-15',
            'gender' => 'male',
            'address' => '123 Main St',
            'phone_number' => '+639171234567',
            'phone_country_code' => 'PH',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'device_name' => 'phpunit-test',
        ], $overrides);
    };
});

it('records a signups metric with the user role category on registration', function () {
    $this->postJson('/api/v1/register', ($this->validPayload)())->assertCreated();

    Metrics::assertRecorded(
        fn (Measurable $metric) => $metric->name() === 'signups' && $metric->category() === Role::User->value
    );
});

it('records a qr:scanned metric when a scan is logged', function () {
    $scanner = User::factory()->create();
    $scanned = User::factory()->create();
    Sanctum::actingAs($scanner, ['*']);

    $this->postJson('/api/v1/scans', [
        'scanned_user_id' => $scanned->id,
    ])->assertCreated();

    Metrics::assertRecorded('qr:scanned');
});

it('records an auth:logins metric on successful login', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    Metrics::assertRecorded('auth:logins');
});

it('records a users:deactivated metric when an admin deactivates a user', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    $target = User::factory()->create();
    $target->assignRole(Role::User->value);

    $this->actingAs($admin)->patch(route('admin.users.activation', $target));

    Metrics::assertRecorded('users:deactivated');
});

it('does not record a users:deactivated metric when reactivating a user', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::Admin->value);

    $target = User::factory()->create(['deactivated_at' => now()]);
    $target->assignRole(Role::User->value);

    $this->actingAs($admin)->patch(route('admin.users.activation', $target));

    Metrics::assertNotRecorded('users:deactivated');
});

<?php

use App\Enums\Gender;
use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->validPayload = function (array $overrides = []): array {
        return array_merge([
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'device_name' => 'phpunit-test',
            'first_name' => 'Juan',
            'last_name' => 'dela Cruz',
            'date_of_birth' => '1990-06-15',
            'gender' => 'male',
        ], $overrides);
    };
});

it('users can login via api and receive a token', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'phpunit-test',
    ]);

    $response->assertOk()->assertJsonStructure(['data' => ['token', 'user' => ['id', 'email']]]);
    $this->assertDatabaseCount('personal_access_tokens', 1);
});

it('users cannot login with invalid password via api', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
        'device_name' => 'phpunit-test',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('login validation requires email, password, and device_name', function () {
    $this->postJson('/api/v1/login', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password', 'device_name']);
});

it('deactivated users cannot login via api', function () {
    $user = User::factory()->create(['deactivated_at' => now()]);

    $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'phpunit-test',
    ])->assertUnprocessable();
});

it('login token abilities match the users roles and permissions', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignRole(Role::Admin->value);

    $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'phpunit-test',
    ])->assertOk();

    $token = $user->tokens()->first();
    expect($token->abilities)->toEqual($user->tokenAbilities());
    expect($token->abilities)->toContain(Role::Admin->value);
});

it('users can register via api', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $response = $this->postJson('/api/v1/register', ($this->validPayload)());

    $response->assertCreated()->assertJsonStructure(['data' => ['token', 'user']]);
    $this->assertDatabaseHas('users', ['email' => 'test@example.com']);

    $user = User::where('email', 'test@example.com')->first();
    $this->assertTrue($user->hasRole(Role::User->value));
});

it('registration validation rejects a missing required field', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $response = $this->postJson('/api/v1/register', ($this->validPayload)(['first_name' => null]));

    $response->assertUnprocessable()->assertJsonValidationErrors('first_name');
});

it('registration requires device_name', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $payload = ($this->validPayload)();
    unset($payload['device_name']);

    $this->postJson('/api/v1/register', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('device_name');
});

it('registration token name matches the submitted device_name', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->postJson('/api/v1/register', ($this->validPayload)(['device_name' => 'my-iphone']))
        ->assertCreated();

    $user = User::where('email', 'test@example.com')->first();
    expect($user->tokens()->first()->name)->toBe('my-iphone');
});

it('authenticated users can fetch their profile via api', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJson(['data' => ['user' => ['email' => $user->email]]]);
});

it('me includes medical information and allergies when present', function () {
    $user = User::factory()->create();
    $user->medicalInformation()->create([
        'first_name' => 'Ana',
        'last_name' => 'Reyes',
        'date_of_birth' => '1992-04-10',
        'gender' => Gender::Female,
        'no_blood_transfusion' => false,
    ]);
    $user->medicalInformation->allergies()->create([
        'allergen' => 'Peanuts',
        'reaction' => 'Hives',
        'severity' => 'severe',
    ]);

    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJson(['data' => ['user' => [
            'medical_information' => [
                'full_name' => 'Ana Reyes',
                'allergies' => [
                    ['allergen' => 'Peanuts', 'reaction' => 'Hives', 'severity' => 'severe'],
                ],
            ],
        ]]]);
});

it('unauthenticated requests to me are rejected', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

it('users can logout and revoke their current token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('phpunit-test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/logout')
        ->assertOk();

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('deactivated users are rejected on authenticated api routes', function () {
    $user = User::factory()->create(['deactivated_at' => now()]);
    $token = $user->createToken('phpunit-test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/me')
        ->assertForbidden();
});

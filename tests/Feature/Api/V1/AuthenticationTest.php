<?php

use App\Enums\Role;
use App\Models\MedicalInformation;
use App\Models\MedicalInformationRegistrationMatch;
use App\Models\User;
use App\Notifications\MedicalInformationRegistrationMatchNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
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

it('registration rejects an invalid gender', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->postJson('/api/v1/register', ($this->validPayload)(['gender' => 'other']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('gender');
});

it('registration rejects a future dob', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->postJson('/api/v1/register', ($this->validPayload)(['dob' => now()->addDay()->toDateString()]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('dob');
});

it('registration rejects an oversized suffix or address', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->postJson('/api/v1/register', ($this->validPayload)(['suffix' => str_repeat('a', 51)]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('suffix');

    $this->postJson('/api/v1/register', ($this->validPayload)(['address' => str_repeat('a', 1001)]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('address');
});

it('holds a matched registration pending instead of creating an account', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $primary = User::factory()->create();
    $record = MedicalInformation::factory()->create([
        'first_name' => 'Juan',
        'middle_name' => null,
        'last_name' => 'dela Cruz',
        'suffix' => null,
        'dob' => '1990-01-15',
        'primary_user_id' => $primary->id,
    ]);
    $primary->forceFill(['medical_information_id' => $record->id])->save();

    Notification::fake();

    $response = $this->postJson('/api/v1/register', ($this->validPayload)());

    $response->assertStatus(202)->assertJson(['data' => ['pending' => true]]);
    $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    $this->assertDatabaseHas('pending_registrations', ['email' => 'test@example.com']);
    expect(MedicalInformationRegistrationMatch::count())->toBe(1);
    Notification::assertSentTo($primary, MedicalInformationRegistrationMatchNotification::class);
});

it('rejects registration when a pending registration already holds the email', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $primary = User::factory()->create();
    $record = MedicalInformation::factory()->create([
        'first_name' => 'Juan',
        'middle_name' => null,
        'last_name' => 'dela Cruz',
        'suffix' => null,
        'dob' => '1990-01-15',
        'primary_user_id' => $primary->id,
    ]);
    $primary->forceFill(['medical_information_id' => $record->id])->save();

    Notification::fake();

    $this->postJson('/api/v1/register', ($this->validPayload)())->assertStatus(202);

    $this->postJson('/api/v1/register', ($this->validPayload)())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->assertDatabaseCount('pending_registrations', 1);
});

it('registration rejects a malformed phone number', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->postJson('/api/v1/register', ($this->validPayload)(['phone_number' => 'not-a-phone-number']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone_number');
});

it('register response and me endpoint expose the full user resource shape', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $response = $this->postJson('/api/v1/register', ($this->validPayload)([
        'middle_name' => 'Santos',
        'suffix' => 'Jr.',
        'address' => '123 Main St',
        'phone_number' => '+639171234567',
        'phone_country_code' => 'PH',
    ]));

    $response->assertCreated()
        ->assertJson(['data' => [
            'user' => [
                'first_name' => 'Juan',
                'middle_name' => 'Santos',
                'last_name' => 'dela Cruz',
                'suffix' => 'Jr.',
                'fullname' => 'Juan Santos dela Cruz Jr.',
                'dob' => '1990-01-15',
                'gender' => 'male',
                'address' => '123 Main St',
                'phone_number' => '+639171234567',
                'email' => 'test@example.com',
                'is_active' => true,
            ],
        ]]);

    $user = User::where('email', 'test@example.com')->first();
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJson(['data' => ['user' => [
            'fullname' => 'Juan Santos dela Cruz Jr.',
            'age' => $user->age,
            'dob' => '1990-01-15',
            'gender' => 'male',
            'address' => '123 Main St',
            'phone_number' => '+639171234567',
            'is_active' => true,
        ]]]);
});

it('authenticated users can fetch their profile via api', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJson(['data' => ['user' => ['email' => $user->email]]]);
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

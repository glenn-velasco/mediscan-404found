<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Broadcasting\AnonymousEvent;
use Illuminate\Support\Facades\Broadcast;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
    $this->seed(RoleAndPermissionSeeder::class);

    $this->validPayload = function (array $overrides = []): array {
        return array_merge([
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'first_name' => 'Juan',
            'last_name' => 'dela Cruz',
            'date_of_birth' => '1990-06-15',
            'gender' => 'male',
        ], $overrides);
    };
});

it('registration screen can be rendered', function () {
    $this->get(route('register'))->assertOk();
});

it('new users can register', function () {
    $this->post(route('register.store'), ($this->validPayload)())
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
});

it('broadcasts UserRegistered on the admin dashboard channel', function () {
    $event = Mockery::mock(AnonymousEvent::class, ['admin-dashboard'])->makePartial();
    $event->shouldReceive('send')->once();

    Broadcast::shouldReceive('private')
        ->once()
        ->with('admin-dashboard')
        ->andReturn($event);

    $this->post(route('register.store'), ($this->validPayload)())
        ->assertRedirect(route('dashboard'));

    expect($event->broadcastAs())->toBe('UserRegistered');
    expect($event->broadcastWith())->toHaveKey('stats');
});

it('new users are assigned the user role', function () {
    $this->post(route('register.store'), ($this->validPayload)());

    $user = User::where('email', 'test@example.com')->first();
    $this->assertNotNull($user);
    $this->assertTrue($user->hasRole(Role::User->value));
});

it('registration creates medical information', function () {
    $this->post(route('register.store'), ($this->validPayload)([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'date_of_birth' => '1995-03-20',
        'gender' => 'female',
        'blood_type' => 'A+',
        'religion' => 'Catholic',
    ]));

    $user = User::where('email', 'test@example.com')->first();
    $this->assertNotNull($user);

    $mi = $user->medicalInformation;
    $this->assertNotNull($mi);
    $this->assertEquals('Maria', $mi->first_name);
    $this->assertEquals('Santos', $mi->last_name);
    $this->assertEquals('A+', $mi->blood_type);
    $this->assertEquals('Catholic', $mi->religion);
});

it('registration sets user name from first and last name', function () {
    $this->post(route('register.store'), ($this->validPayload)([
        'first_name' => 'Pedro',
        'last_name' => 'Reyes',
    ]));

    $user = User::where('email', 'test@example.com')->first();
    $this->assertEquals('Pedro Reyes', $user->name);
});

it('registration with optional fields', function () {
    $this->post(route('register.store'), ($this->validPayload)([
        'middle_name' => 'Maria',
        'suffix' => 'Jr.',
        'phone_country_code' => 'PH',
        'phone' => '+639171234567',
        'emergency_contact_name' => 'Ana Reyes',
        'emergency_contact_relationship' => 'Spouse',
        'emergency_contact_phone_country_code' => 'PH',
        'emergency_contact_phone' => '+639189876543',
        'no_blood_transfusion' => true,
    ]))->assertRedirect(route('dashboard'));

    $user = User::where('email', 'test@example.com')->first();
    $mi = $user->medicalInformation;

    $this->assertEquals('Maria', $mi->middle_name);
    $this->assertEquals('Jr.', $mi->suffix);
    $this->assertTrue($mi->no_blood_transfusion);
    $this->assertEquals('Ana Reyes', $mi->emergencyContacts()->first()?->name);
});

it('registration requires first name', function () {
    $this->post(route('register.store'), ($this->validPayload)(['first_name' => '']))
        ->assertSessionHasErrors('first_name');
});

it('registration requires last name', function () {
    $this->post(route('register.store'), ($this->validPayload)(['last_name' => '']))
        ->assertSessionHasErrors('last_name');
});

it('registration requires date of birth', function () {
    $this->post(route('register.store'), ($this->validPayload)(['date_of_birth' => '']))
        ->assertSessionHasErrors('date_of_birth');
});

it('registration requires gender', function () {
    $this->post(route('register.store'), ($this->validPayload)(['gender' => '']))
        ->assertSessionHasErrors('gender');
});

it('registration rejects future date of birth', function () {
    $this->post(route('register.store'), ($this->validPayload)(['date_of_birth' => now()->addYear()->toDateString()]))
        ->assertSessionHasErrors('date_of_birth');
});

it('registration rejects invalid gender', function () {
    $this->post(route('register.store'), ($this->validPayload)(['gender' => 'other']))
        ->assertSessionHasErrors('gender');
});

it('registration rejects duplicate email', function () {
    User::factory()->create(['email' => 'test@example.com']);

    $this->post(route('register.store'), ($this->validPayload)())
        ->assertSessionHasErrors('email');
});

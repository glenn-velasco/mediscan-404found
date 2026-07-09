<?php

use App\Enums\Gender;
use App\Events\EmergencyContactCreated;
use App\Events\EmergencyContactDeleted;
use App\Events\EmergencyContactUpdated;
use App\Models\EmergencyContact;
use App\Models\User;
use App\Services\User\MedicalInfoService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->withMedicalInfo = function (User $user): User {
        $user->medicalInformation()->create([
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'date_of_birth' => '1992-04-10',
            'gender' => Gender::Female,
            'no_blood_transfusion' => false,
        ]);

        return $user;
    };

    $this->contactFor = function (User $user, array $overrides = []): EmergencyContact {
        return $user->medicalInformation->emergencyContacts()->create(array_merge([
            'name' => 'Jane Contact',
            'relationship' => 'Spouse',
            'phone_country_code' => 'PH',
            'phone' => '9171234567',
            'is_primary' => false,
        ], $overrides));
    };
});

// --- Index ---

it('lists emergency contacts via api', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    ($this->contactFor)($user, ['name' => 'Contact A', 'is_primary' => true]);
    ($this->contactFor)($user, ['name' => 'Contact B', 'is_primary' => false]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/emergency-contacts')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Contact A')
        ->assertJsonPath('data.1.name', 'Contact B');
});

it('returns emergency contacts ordered by primary first then by newest', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    ($this->contactFor)($user, ['name' => 'Old', 'is_primary' => true]);
    ($this->contactFor)($user, ['name' => 'New', 'is_primary' => false]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/emergency-contacts')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Old')
        ->assertJsonPath('data.0.is_primary', true)
        ->assertJsonPath('data.1.name', 'New')
        ->assertJsonPath('data.1.is_primary', false);
});

it('returns paginated emergency contacts via api', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    ($this->contactFor)($user, ['name' => 'Contact A', 'is_primary' => true]);
    ($this->contactFor)($user, ['name' => 'Contact B', 'is_primary' => false]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/emergency-contacts?per_page=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonStructure(['data', 'links', 'meta']);
});

it('returns empty list when user has no emergency contacts via api', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/emergency-contacts')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('returns correct response structure for emergency contacts', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    ($this->contactFor)($user, ['is_primary' => true]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/emergency-contacts')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'name',
                    'relationship',
                    'phone_country_code',
                    'phone',
                    'is_primary',
                    'created_at',
                ],
            ],
        ]);
});

it('unauthenticated users cannot list emergency contacts', function () {
    $this->getJson('/api/v1/emergency-contacts')->assertUnauthorized();
});

// --- Store ---

it('creates an emergency contact via api', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/emergency-contacts', [
        'name' => 'John Doe',
        'relationship' => 'Spouse',
        'phone_country_code' => 'PH',
        'phone' => '9171234567',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'John Doe')
        ->assertJsonPath('data.relationship', 'Spouse')
        ->assertJsonPath('data.is_primary', true);

    $this->assertDatabaseHas('emergency_contacts', [
        'medical_information_id' => $user->medicalInformation->id,
        'name' => 'John Doe',
    ]);
});

it('creates an emergency contact with only name via api', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/emergency-contacts', [
        'name' => 'Minimal Contact',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Minimal Contact')
        ->assertJsonPath('data.relationship', null)
        ->assertJsonPath('data.phone', null)
        ->assertJsonPath('data.is_primary', true);
});

it('first emergency contact is automatically primary via api', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/emergency-contacts', [
        'name' => 'First Contact',
        'is_primary' => false,
    ])->assertCreated();

    $this->assertDatabaseHas('emergency_contacts', [
        'medical_information_id' => $user->medicalInformation->id,
        'name' => 'First Contact',
        'is_primary' => true,
    ]);
});

it('creating a second contact as primary unmarks the first via api', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    $first = ($this->contactFor)($user, ['is_primary' => true]);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/emergency-contacts', [
        'name' => 'Second Contact',
        'is_primary' => true,
    ])->assertCreated();

    $this->assertDatabaseHas('emergency_contacts', ['id' => $first->id, 'is_primary' => false]);
    $this->assertDatabaseHas('emergency_contacts', ['name' => 'Second Contact', 'is_primary' => true]);
});

it('creating a second contact without primary flag does not disturb existing primary via api', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    $first = ($this->contactFor)($user, ['is_primary' => true]);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/emergency-contacts', [
        'name' => 'Second Contact',
    ])->assertCreated();

    $this->assertDatabaseHas('emergency_contacts', ['id' => $first->id, 'is_primary' => true]);
    $this->assertDatabaseHas('emergency_contacts', ['name' => 'Second Contact', 'is_primary' => false]);
});

it('store emergency contact validation errors via api', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/emergency-contacts', ['name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('validates phone with country code via api', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/emergency-contacts', [
        'name' => 'Contact',
        'phone_country_code' => 'PH',
        'phone' => 'not-a-phone',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone']);
});

it('cannot create emergency contact without medical information via api', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/emergency-contacts', ['name' => 'John'])
        ->assertNotFound();
});

it('unauthenticated users cannot create emergency contact', function () {
    $this->postJson('/api/v1/emergency-contacts', ['name' => 'John'])
        ->assertUnauthorized();
});

it('broadcasts EmergencyContactCreated via api', function () {
    Event::fake([EmergencyContactCreated::class]);

    $user = ($this->withMedicalInfo)(User::factory()->create());
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/emergency-contacts', [
        'name' => 'John Doe',
    ])->assertCreated();

    Event::assertDispatched(EmergencyContactCreated::class, fn (EmergencyContactCreated $event) => $event->userId === $user->id
        && $event->name === 'John Doe');
});

// --- Update ---

it('updates an emergency contact via api', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    $contact = ($this->contactFor)($user, ['is_primary' => true]);
    Sanctum::actingAs($user, ['*']);

    $this->patchJson("/api/v1/emergency-contacts/{$contact->id}", [
        'name' => 'Updated Name',
        'relationship' => 'Parent',
        'is_primary' => true,
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Name')
        ->assertJsonPath('data.relationship', 'Parent');

    $this->assertDatabaseHas('emergency_contacts', [
        'id' => $contact->id,
        'name' => 'Updated Name',
        'relationship' => 'Parent',
    ]);
});

it('marking a different contact primary unmarks the previous one via api', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    $a = ($this->contactFor)($user, ['name' => 'A', 'is_primary' => true]);
    $b = ($this->contactFor)($user, ['name' => 'B', 'is_primary' => false]);
    Sanctum::actingAs($user, ['*']);

    $this->patchJson("/api/v1/emergency-contacts/{$b->id}", [
        'name' => 'B',
        'is_primary' => true,
    ])->assertOk();

    $this->assertDatabaseHas('emergency_contacts', ['id' => $a->id, 'is_primary' => false]);
    $this->assertDatabaseHas('emergency_contacts', ['id' => $b->id, 'is_primary' => true]);
});

it('patching is primary false on sole primary contact is allowed and leaves zero primary via api', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    $contact = ($this->contactFor)($user, ['is_primary' => true]);
    Sanctum::actingAs($user, ['*']);

    $this->patchJson("/api/v1/emergency-contacts/{$contact->id}", [
        'name' => 'Jane Contact',
        'is_primary' => false,
    ])->assertOk();

    $this->assertDatabaseHas('emergency_contacts', ['id' => $contact->id, 'is_primary' => false]);
    $this->assertDatabaseMissing('emergency_contacts', [
        'medical_information_id' => $user->medicalInformation->id,
        'is_primary' => true,
    ]);
});

it('update emergency contact validation errors via api', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    $contact = ($this->contactFor)($user, ['is_primary' => true]);
    Sanctum::actingAs($user, ['*']);

    $this->patchJson("/api/v1/emergency-contacts/{$contact->id}", ['name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('users cannot update another users emergency contact via api', function () {
    $owner = ($this->withMedicalInfo)(User::factory()->create());
    $contact = ($this->contactFor)($owner, ['is_primary' => true]);

    $intruder = ($this->withMedicalInfo)(User::factory()->create());
    Sanctum::actingAs($intruder, ['*']);

    $this->patchJson("/api/v1/emergency-contacts/{$contact->id}", [
        'name' => 'Hacked',
    ])->assertNotFound();

    $this->assertDatabaseHas('emergency_contacts', ['id' => $contact->id, 'name' => 'Jane Contact']);
});

it('returns not found when updating a nonexistent emergency contact via api', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    $contact = ($this->contactFor)($user, ['is_primary' => true]);
    $deletedId = $contact->id;
    $contact->delete();
    Sanctum::actingAs($user, ['*']);

    $this->patchJson("/api/v1/emergency-contacts/{$deletedId}", ['name' => 'Jane Contact'])
        ->assertNotFound();
});

it('unauthenticated users cannot update emergency contact', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    $contact = ($this->contactFor)($user, ['is_primary' => true]);

    $this->patchJson("/api/v1/emergency-contacts/{$contact->id}", [
        'name' => 'Hacked',
    ])->assertUnauthorized();
});

it('broadcasts EmergencyContactUpdated via api', function () {
    Event::fake([EmergencyContactUpdated::class]);

    $user = ($this->withMedicalInfo)(User::factory()->create());
    $contact = ($this->contactFor)($user, ['is_primary' => true]);
    Sanctum::actingAs($user, ['*']);

    $this->patchJson("/api/v1/emergency-contacts/{$contact->id}", [
        'name' => 'Updated Name',
    ])->assertOk();

    Event::assertDispatched(EmergencyContactUpdated::class, fn (EmergencyContactUpdated $event) => $event->userId === $user->id
        && $event->emergencyContactId === $contact->id
        && $event->name === 'Updated Name');
});

// --- Delete ---

it('deletes an emergency contact via api', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    $contact = ($this->contactFor)($user, ['is_primary' => true]);
    Sanctum::actingAs($user, ['*']);

    $this->deleteJson("/api/v1/emergency-contacts/{$contact->id}")
        ->assertOk();

    $this->assertModelMissing($contact);
});

it('deleting the primary contact leaves zero primary contacts via api', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    $primary = ($this->contactFor)($user, ['name' => 'Primary', 'is_primary' => true]);
    $other = ($this->contactFor)($user, ['name' => 'Other', 'is_primary' => false]);
    Sanctum::actingAs($user, ['*']);

    $this->deleteJson("/api/v1/emergency-contacts/{$primary->id}")->assertOk();

    $this->assertModelMissing($primary);
    $this->assertDatabaseHas('emergency_contacts', ['id' => $other->id, 'is_primary' => false]);
    $this->assertDatabaseMissing('emergency_contacts', [
        'medical_information_id' => $user->medicalInformation->id,
        'is_primary' => true,
    ]);
});

it('users cannot delete another users emergency contact via api', function () {
    $owner = ($this->withMedicalInfo)(User::factory()->create());
    $contact = ($this->contactFor)($owner, ['is_primary' => true]);

    $intruder = ($this->withMedicalInfo)(User::factory()->create());
    Sanctum::actingAs($intruder, ['*']);

    $this->deleteJson("/api/v1/emergency-contacts/{$contact->id}")->assertNotFound();

    $this->assertModelExists($contact);
});

it('returns not found when deleting a nonexistent emergency contact via api', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    $contact = ($this->contactFor)($user, ['is_primary' => true]);
    $deletedId = $contact->id;
    $contact->delete();
    Sanctum::actingAs($user, ['*']);

    $this->deleteJson("/api/v1/emergency-contacts/{$deletedId}")->assertNotFound();
});

it('unauthenticated users cannot delete emergency contact', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    $contact = ($this->contactFor)($user, ['is_primary' => true]);

    $this->deleteJson("/api/v1/emergency-contacts/{$contact->id}")->assertUnauthorized();
});

it('broadcasts EmergencyContactDeleted via api', function () {
    Event::fake([EmergencyContactDeleted::class]);

    $user = ($this->withMedicalInfo)(User::factory()->create());
    $contact = ($this->contactFor)($user, ['is_primary' => true]);
    Sanctum::actingAs($user, ['*']);

    $this->deleteJson("/api/v1/emergency-contacts/{$contact->id}")->assertOk();

    Event::assertDispatched(EmergencyContactDeleted::class, fn (EmergencyContactDeleted $event) => $event->userId === $user->id
        && $event->emergencyContactId === $contact->id);
});

it('returns correct response structure for single emergency contact', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    ($this->contactFor)($user, ['is_primary' => true]);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/emergency-contacts')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'name',
                    'relationship',
                    'phone_country_code',
                    'phone',
                    'is_primary',
                    'created_at',
                ],
            ],
        ]);
});

// --- Cache flush ---

it('flushes dashboard cache on create', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    Sanctum::actingAs($user);

    Cache::put(MedicalInfoService::cacheKey($user->id), ['stale'], now()->addMonth());

    $this->postJson('/api/v1/emergency-contacts', [
        'name' => 'Cached Contact',
    ])->assertCreated();

    expect(Cache::has(MedicalInfoService::cacheKey($user->id)))->toBeFalse();
});

it('flushes dashboard cache on update', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    Sanctum::actingAs($user);

    $contact = ($this->contactFor)($user);

    Cache::put(MedicalInfoService::cacheKey($user->id), ['stale'], now()->addMonth());

    $this->patchJson("/api/v1/emergency-contacts/{$contact->id}", [
        'name' => 'Updated Name',
    ])->assertOk();

    expect(Cache::has(MedicalInfoService::cacheKey($user->id)))->toBeFalse();
});

it('flushes dashboard cache on destroy', function () {
    $user = ($this->withMedicalInfo)(User::factory()->create());
    Sanctum::actingAs($user);

    $contact = ($this->contactFor)($user);

    Cache::put(MedicalInfoService::cacheKey($user->id), ['stale'], now()->addMonth());

    $this->deleteJson("/api/v1/emergency-contacts/{$contact->id}")
        ->assertOk();

    expect(Cache::has(MedicalInfoService::cacheKey($user->id)))->toBeFalse();
});

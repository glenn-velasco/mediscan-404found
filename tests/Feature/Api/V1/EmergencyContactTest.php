<?php

use App\Models\EmergencyContact;
use App\Models\MedicalInformation;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function actingAsEmergencyContactOwner(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $medicalInformation = MedicalInformation::factory()->create();
    $user->forceFill(['medical_information_id' => $medicalInformation->id])->save();

    Sanctum::actingAs($user->fresh(), ['*']);

    return $user->fresh();
}

it('creates an emergency contact on the authenticated users own medical information', function () {
    $user = actingAsEmergencyContactOwner();
    $id = (string) Str::uuid();

    $this->postJson('/api/v1/emergency-contacts', [
        'id' => $id,
        'name' => 'Maria Dela Cruz',
        'relationship' => 'spouse',
        'phone_country_code' => '63',
        'phone' => '9171234567',
        'is_primary' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Maria Dela Cruz')
        ->assertJsonPath('data.is_primary', true);

    expect(EmergencyContact::query()->whereKey($id)->first()?->medical_information_id)->toBe($user->medical_information_id);
});

it('lists only the authenticated users own emergency contacts', function () {
    $user = actingAsEmergencyContactOwner();
    EmergencyContact::factory()->count(2)->create(['medical_information_id' => $user->medical_information_id]);
    EmergencyContact::factory()->create();

    $this->getJson('/api/v1/emergency-contacts')->assertOk()->assertJsonCount(2, 'data');
});

it('returns 404 for an emergency contact owned by another user', function () {
    $emergencyContact = EmergencyContact::factory()->create();
    actingAsEmergencyContactOwner();

    $this->getJson("/api/v1/emergency-contacts/{$emergencyContact->id}")->assertNotFound();
});

it('updates an owned emergency contact', function () {
    $user = actingAsEmergencyContactOwner();
    $emergencyContact = EmergencyContact::factory()->create(['medical_information_id' => $user->medical_information_id]);

    $this->putJson("/api/v1/emergency-contacts/{$emergencyContact->id}", [
        'name' => 'Juan Dela Cruz',
        'relationship' => 'parent',
    ])->assertOk()->assertJsonPath('data.name', 'Juan Dela Cruz');
});

it('soft deletes an owned emergency contact', function () {
    $user = actingAsEmergencyContactOwner();
    $emergencyContact = EmergencyContact::factory()->create(['medical_information_id' => $user->medical_information_id]);

    $this->deleteJson("/api/v1/emergency-contacts/{$emergencyContact->id}")->assertOk();

    expect(EmergencyContact::query()->whereKey($emergencyContact->id)->exists())->toBeFalse();
});

it('encrypts name and phone at rest', function () {
    $user = actingAsEmergencyContactOwner();
    $emergencyContact = EmergencyContact::factory()->create([
        'medical_information_id' => $user->medical_information_id,
        'name' => 'Maria Dela Cruz',
        'phone' => '9171234567',
    ]);

    $raw = DB::table('emergency_contacts')->where('id', $emergencyContact->id)->first();

    expect($raw->name)->not->toContain('Maria Dela Cruz')
        ->and($raw->phone)->not->toContain('9171234567');
});

<?php

use App\Models\Allergy;
use App\Models\AuditLog;
use App\Models\MedicalInformation;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function actingAsUserWithMedicalInformation(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $medicalInformation = MedicalInformation::factory()->create();
    $user->forceFill(['medical_information_id' => $medicalInformation->id])->save();

    Sanctum::actingAs($user->fresh(), ['*']);

    return $user->fresh();
}

it('creates an allergy on the authenticated users own medical information', function () {
    $user = actingAsUserWithMedicalInformation();
    $id = (string) Str::uuid();

    $this->postJson('/api/v1/allergies', [
        'id' => $id,
        'allergen' => 'Peanuts',
        'reaction' => 'Hives',
        'severity' => 'severe',
    ])
        ->assertCreated()
        ->assertJsonPath('data.id', $id)
        ->assertJsonPath('data.allergen', 'Peanuts')
        ->assertJsonPath('data.severity', 'severe');

    expect(Allergy::query()->whereKey($id)->first()?->medical_information_id)->toBe($user->medical_information_id);
});

it('rejects creating an allergy without a linked medical information record', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/allergies', [
        'id' => (string) Str::uuid(),
        'allergen' => 'Peanuts',
        'severity' => 'severe',
    ])->assertStatus(422);
});

it('rejects a duplicate id on create', function () {
    actingAsUserWithMedicalInformation();
    $allergy = Allergy::factory()->create();

    $this->postJson('/api/v1/allergies', [
        'id' => $allergy->id,
        'allergen' => 'Peanuts',
        'severity' => 'severe',
    ])->assertJsonValidationErrors('id');
});

it('lists only the authenticated users own allergies', function () {
    $user = actingAsUserWithMedicalInformation();
    Allergy::factory()->count(2)->create(['medical_information_id' => $user->medical_information_id]);
    Allergy::factory()->create(); // belongs to someone else

    $this->getJson('/api/v1/allergies')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('shows an owned allergy', function () {
    $user = actingAsUserWithMedicalInformation();
    $allergy = Allergy::factory()->create(['medical_information_id' => $user->medical_information_id]);

    $this->getJson("/api/v1/allergies/{$allergy->id}")
        ->assertOk()
        ->assertJsonPath('data.allergen', $allergy->allergen);
});

it('returns 404 for an allergy owned by another user', function () {
    $allergy = Allergy::factory()->create();
    actingAsUserWithMedicalInformation();

    $this->getJson("/api/v1/allergies/{$allergy->id}")->assertNotFound();
});

it('updates an owned allergy and logs the audit fields changed, not the values', function () {
    $user = actingAsUserWithMedicalInformation();
    $allergy = Allergy::factory()->create(['medical_information_id' => $user->medical_information_id, 'allergen' => 'Peanuts']);

    $this->putJson("/api/v1/allergies/{$allergy->id}", [
        'allergen' => 'Shellfish',
        'severity' => 'mild',
    ])
        ->assertOk()
        ->assertJsonPath('data.allergen', 'Shellfish');

    $log = AuditLog::query()->where('action', 'allergy.updated')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->record_type)->toBe('allergy')
        ->and($log->record_id)->toBe($allergy->id)
        ->and($log->metadata['fields_changed'])->toContain('allergen')
        ->and(json_encode($log->metadata))->not->toContain('Shellfish');
});

it('rejects updating another users allergy', function () {
    $allergy = Allergy::factory()->create();
    actingAsUserWithMedicalInformation();

    $this->putJson("/api/v1/allergies/{$allergy->id}", [
        'allergen' => 'Shellfish',
        'severity' => 'mild',
    ])->assertNotFound();
});

it('soft deletes an owned allergy', function () {
    $user = actingAsUserWithMedicalInformation();
    $allergy = Allergy::factory()->create(['medical_information_id' => $user->medical_information_id]);

    $this->deleteJson("/api/v1/allergies/{$allergy->id}")->assertOk();

    expect(Allergy::query()->whereKey($allergy->id)->exists())->toBeFalse()
        ->and(Allergy::withTrashed()->whereKey($allergy->id)->exists())->toBeTrue();

    // Soft-deleted rows 404 on subsequent lookups - the mobile sync layer
    // still sees them via GET /sync's withTrashed() query, not this route.
    $this->getJson("/api/v1/allergies/{$allergy->id}")->assertNotFound();
});

it('encrypts allergen and reaction at rest', function () {
    $user = actingAsUserWithMedicalInformation();
    $allergy = Allergy::factory()->create([
        'medical_information_id' => $user->medical_information_id,
        'allergen' => 'Peanuts',
        'reaction' => 'Anaphylaxis',
    ]);

    $raw = DB::table('allergies')->where('id', $allergy->id)->first();

    expect($raw->allergen)->not->toContain('Peanuts')
        ->and($raw->reaction)->not->toContain('Anaphylaxis')
        ->and($raw->severity)->toBe($allergy->severity->value); // not encrypted
});

<?php

use App\Models\AuditLog;
use App\Models\Condition;
use App\Models\MedicalInformation;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function actingAsUserWithMedicalInformationForConditions(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $medicalInformation = MedicalInformation::factory()->create();
    $user->forceFill(['medical_information_id' => $medicalInformation->id])->save();

    Sanctum::actingAs($user->fresh(), ['*']);

    return $user->fresh();
}

it('creates a condition on the authenticated users own medical information', function () {
    $user = actingAsUserWithMedicalInformationForConditions();
    $id = (string) Str::uuid();

    $this->postJson('/api/v1/conditions', [
        'id' => $id,
        'description' => 'Occasional migraines',
    ])
        ->assertCreated()
        ->assertJsonPath('data.id', $id)
        ->assertJsonPath('data.description', 'Occasional migraines');

    expect(Condition::query()->whereKey($id)->first()?->medical_information_id)->toBe($user->medical_information_id);
});

it('rejects creating a condition without a linked medical information record', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/conditions', [
        'id' => (string) Str::uuid(),
        'description' => 'Occasional migraines',
    ])->assertStatus(422);
});

it('rejects a duplicate id on create', function () {
    actingAsUserWithMedicalInformationForConditions();
    $condition = Condition::factory()->create();

    $this->postJson('/api/v1/conditions', [
        'id' => $condition->id,
        'description' => 'Occasional migraines',
    ])->assertJsonValidationErrors('id');
});

it('lists only the authenticated users own conditions', function () {
    $user = actingAsUserWithMedicalInformationForConditions();
    Condition::factory()->count(2)->create(['medical_information_id' => $user->medical_information_id]);
    Condition::factory()->create(); // belongs to someone else

    $this->getJson('/api/v1/conditions')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('shows an owned condition', function () {
    $user = actingAsUserWithMedicalInformationForConditions();
    $condition = Condition::factory()->create(['medical_information_id' => $user->medical_information_id]);

    $this->getJson("/api/v1/conditions/{$condition->id}")
        ->assertOk()
        ->assertJsonPath('data.description', $condition->description);
});

it('returns 404 for a condition owned by another user', function () {
    $condition = Condition::factory()->create();
    actingAsUserWithMedicalInformationForConditions();

    $this->getJson("/api/v1/conditions/{$condition->id}")->assertNotFound();
});

it('updates an owned condition and logs the audit fields changed, not the values', function () {
    $user = actingAsUserWithMedicalInformationForConditions();
    $condition = Condition::factory()->create(['medical_information_id' => $user->medical_information_id, 'description' => 'Occasional migraines']);

    $this->putJson("/api/v1/conditions/{$condition->id}", [
        'description' => 'Seasonal allergies',
    ])
        ->assertOk()
        ->assertJsonPath('data.description', 'Seasonal allergies');

    $log = AuditLog::query()->where('action', 'condition.updated')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->record_type)->toBe('condition')
        ->and($log->record_id)->toBe($condition->id)
        ->and($log->metadata['fields_changed'])->toContain('description')
        ->and(json_encode($log->metadata))->not->toContain('Seasonal allergies');
});

it('rejects updating another users condition', function () {
    $condition = Condition::factory()->create();
    actingAsUserWithMedicalInformationForConditions();

    $this->putJson("/api/v1/conditions/{$condition->id}", [
        'description' => 'Seasonal allergies',
    ])->assertNotFound();
});

it('soft deletes an owned condition', function () {
    $user = actingAsUserWithMedicalInformationForConditions();
    $condition = Condition::factory()->create(['medical_information_id' => $user->medical_information_id]);

    $this->deleteJson("/api/v1/conditions/{$condition->id}")->assertOk();

    expect(Condition::query()->whereKey($condition->id)->exists())->toBeFalse()
        ->and(Condition::withTrashed()->whereKey($condition->id)->exists())->toBeTrue();

    $this->getJson("/api/v1/conditions/{$condition->id}")->assertNotFound();
});

it('encrypts description at rest', function () {
    $user = actingAsUserWithMedicalInformationForConditions();
    $condition = Condition::factory()->create([
        'medical_information_id' => $user->medical_information_id,
        'description' => 'Occasional migraines',
    ]);

    $raw = DB::table('conditions')->where('id', $condition->id)->first();

    expect($raw->description)->not->toContain('Occasional migraines');
});

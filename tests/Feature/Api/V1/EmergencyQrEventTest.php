<?php

use App\Enums\AuditLogType;
use App\Models\AuditLog;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('logs a shown event', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/emergency-qr/events', [
        'action' => 'shown',
        'patient_id' => $user->id,
    ])->assertCreated();

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $user->id,
        'subject_id' => $user->id,
        'action' => 'emergency_qr.shown',
        'type' => AuditLogType::View->value,
    ]);
});

it('logs a scanned event with a context', function () {
    $actor = User::factory()->create();
    $patient = User::factory()->create();
    Sanctum::actingAs($actor, ['*']);

    $this->postJson('/api/v1/emergency-qr/events', [
        'action' => 'scanned',
        'patient_id' => $patient->id,
        'context' => 'professional_submit',
    ])->assertCreated();

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $actor->id,
        'subject_id' => $patient->id,
        'action' => 'emergency_qr.scanned',
        'type' => AuditLogType::View->value,
    ]);

    expect(
        AuditLog::where('action', 'emergency_qr.scanned')->first()->metadata['context'] ?? null
    )->toBe('professional_submit');
});

it('never 404s for an unknown patient_id', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/emergency-qr/events', [
        'action' => 'scanned',
        'patient_id' => 999999,
    ])->assertCreated();

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $user->id,
        'subject_id' => null,
        'action' => 'emergency_qr.scanned',
    ]);
});

it('rejects an invalid action', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/emergency-qr/events', [
        'action' => 'deleted',
        'patient_id' => $user->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('action');
});

it('requires authentication', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/emergency-qr/events', [
        'action' => 'shown',
        'patient_id' => $user->id,
    ])->assertUnauthorized();
});

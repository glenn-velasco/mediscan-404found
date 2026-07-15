<?php

use App\Models\AuditLog;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('logs a scan event', function () {
    $scanner = User::factory()->create();
    $scanned = User::factory()->create();
    Sanctum::actingAs($scanner, ['*']);

    $this->postJson('/api/v1/scans', [
        'scanned_user_id' => $scanned->id,
    ])->assertCreated();

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $scanner->id,
        'subject_id' => $scanned->id,
        'action' => 'qr.scanned',
        'type' => 'view',
    ]);
});

it('logs a scan event with context', function () {
    $scanner = User::factory()->create();
    $scanned = User::factory()->create();
    Sanctum::actingAs($scanner, ['*']);

    $this->postJson('/api/v1/scans', [
        'scanned_user_id' => $scanned->id,
        'context' => 'qr_scan',
    ])->assertCreated();

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $scanner->id,
        'subject_id' => $scanned->id,
        'action' => 'qr.scanned',
        'type' => 'view',
    ]);

    expect(
        AuditLog::where('action', 'qr.scanned')->first()->metadata['context'] ?? null
    )->toBe('qr_scan');
});

it('rejects a non-existent scanned_user_id', function () {
    $scanner = User::factory()->create();
    Sanctum::actingAs($scanner, ['*']);

    $this->postJson('/api/v1/scans', [
        'scanned_user_id' => 999999,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('scanned_user_id');
});

it('rejects an invalid context', function () {
    $scanner = User::factory()->create();
    $scanned = User::factory()->create();
    Sanctum::actingAs($scanner, ['*']);

    $this->postJson('/api/v1/scans', [
        'scanned_user_id' => $scanned->id,
        'context' => 'invalid_context',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('context');
});

it('requires authentication', function () {
    $scanned = User::factory()->create();

    $this->postJson('/api/v1/scans', [
        'scanned_user_id' => $scanned->id,
    ])->assertUnauthorized();
});

<?php

use App\Models\ProfessionalApplication;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('s3');
    Queue::fake();

    $this->payload = fn (array $overrides = []): array => array_merge([
        'id_type' => 'ph_prc',
        'date_of_birth' => '1990-05-20',
        'id_photo' => UploadedFile::fake()->image('id.jpg'),
        'selfie_frames' => [
            UploadedFile::fake()->image('selfie-0.jpg'),
            UploadedFile::fake()->image('selfie-1.jpg'),
            UploadedFile::fake()->image('selfie-2.jpg'),
        ],
        'flash_frames' => [
            UploadedFile::fake()->image('flash-red.jpg'),
            UploadedFile::fake()->image('flash-green.jpg'),
            UploadedFile::fake()->image('flash-blue.jpg'),
        ],
        'flash_colors' => ['red', 'green', 'blue'],
    ], $overrides);
});

it('submits a professional application via the api', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/professional-applications', ($this->payload)())
        ->assertCreated()
        ->assertJsonPath('data.id_type', 'ph_prc')
        ->assertJsonPath('data.date_of_birth', '1990-05-20')
        ->assertJsonPath('data.status', 'processing');

    expect(ProfessionalApplication::where('user_id', $user->id)->exists())->toBeTrue();
});

it('lists only the authenticated users own application', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/professional-applications', ($this->payload)());

    $this->getJson('/api/v1/professional-applications')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('allows submission from an unverified user', function () {
    $user = User::factory()->unverified()->create();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/professional-applications', ($this->payload)())
        ->assertCreated();

    expect(ProfessionalApplication::where('user_id', $user->id)->exists())->toBeTrue();
});

it('returns 404 when viewing another users application', function () {
    $owner = User::factory()->create();
    Sanctum::actingAs($owner, ['*']);
    $this->postJson('/api/v1/professional-applications', ($this->payload)());
    $application = ProfessionalApplication::where('user_id', $owner->id)->firstOrFail();

    $other = User::factory()->create();
    Sanctum::actingAs($other, ['*']);

    $this->getJson("/api/v1/professional-applications/{$application->id}")
        ->assertNotFound();
});

it('still shows a denied (soft-deleted) application to its owner', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/professional-applications', ($this->payload)());
    $application = ProfessionalApplication::where('user_id', $user->id)->firstOrFail();
    $application->update(['status' => \App\Enums\WorkflowStatus::Denied, 'rejection_reason' => 'Blurry ID photo']);
    $application->delete();

    $this->getJson("/api/v1/professional-applications/{$application->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'denied')
        ->assertJsonPath('data.rejection_reason', 'Blurry ID photo');
});

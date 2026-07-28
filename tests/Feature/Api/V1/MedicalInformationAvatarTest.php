<?php

use App\Models\MedicalInformation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('s3');
});

it('sets the avatar from an uploaded photo', function () {
    $medicalInformation = MedicalInformation::factory()->create();
    $user = User::factory()->create(['medical_information_id' => $medicalInformation->id]);
    Sanctum::actingAs($user, ['*']);

    $this->postJson("/api/v1/medical-information/{$medicalInformation->id}/avatar", [
        'avatar' => UploadedFile::fake()->image('avatar.jpg'),
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Avatar updated.');

    $fresh = $medicalInformation->fresh();
    expect($fresh->avatar_path)->not->toBeNull()
        ->and($fresh->avatar)->not->toBeNull()
        ->and($user->fresh()->profile_photo_path)->toBe($fresh->avatar_path);

    Storage::disk('s3')->assertExists($fresh->avatar_path);
});

it('rejects a non-image upload', function () {
    $medicalInformation = MedicalInformation::factory()->create();
    $user = User::factory()->create(['medical_information_id' => $medicalInformation->id]);
    Sanctum::actingAs($user, ['*']);

    $this->postJson("/api/v1/medical-information/{$medicalInformation->id}/avatar", [
        'avatar' => UploadedFile::fake()->create('not-an-image.pdf', 100),
    ])->assertStatus(422);

    expect($medicalInformation->fresh()->avatar_path)->toBeNull();
});

it('returns 404 for a user not linked to the medical information record', function () {
    $medicalInformation = MedicalInformation::factory()->create();
    $other = User::factory()->create();
    Sanctum::actingAs($other, ['*']);

    $this->postJson("/api/v1/medical-information/{$medicalInformation->id}/avatar", [
        'avatar' => UploadedFile::fake()->image('avatar.jpg'),
    ])->assertNotFound();
});

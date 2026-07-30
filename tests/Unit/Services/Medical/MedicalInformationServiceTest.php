<?php

use App\Enums\Gender;
use App\Models\MedicalInformation;
use App\Models\User;
use App\Services\Medical\MedicalInformationService;

it('matches an existing record by exact name and dob', function () {
    $existing = MedicalInformation::factory()->create([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'dob' => '1990-01-01',
    ]);

    $service = app(MedicalInformationService::class);

    $match = $service->findLinkCandidate([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'suffix' => null,
    ], '1990-01-01');

    expect($match?->id)->toBe($existing->id);
});

it('does not match when the dob differs even if the name is identical', function () {
    MedicalInformation::factory()->create([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'dob' => '1990-01-01',
    ]);

    $service = app(MedicalInformationService::class);

    $match = $service->findLinkCandidate([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'suffix' => null,
    ], '1985-05-05');

    expect($match)->toBeNull();
});

it('treats more than one candidate as ambiguous, never a match', function () {
    MedicalInformation::factory()->count(2)->create([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'dob' => '1990-01-01',
    ]);

    $service = app(MedicalInformationService::class);

    $match = $service->findLinkCandidate([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'suffix' => null,
    ], '1990-01-01');

    expect($match)->toBeNull();
});

it('returns the primary-owned record when duplicates exist but only one is claimed', function () {
    $primary = User::factory()->create();
    $claimed = MedicalInformation::factory()->create([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'dob' => '1990-01-01',
        'primary_user_id' => $primary->id,
    ]);
    // Orphan duplicate (the bug we're fixing)
    MedicalInformation::factory()->create([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'dob' => '1990-01-01',
    ]);

    $service = app(MedicalInformationService::class);

    $match = $service->findLinkCandidate([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'suffix' => null,
    ], '1990-01-01');

    expect($match?->id)->toBe($claimed->id);
});

it('stays ambiguous when multiple duplicates are each claimed by different primaries', function () {
    $primaryA = User::factory()->create();
    $primaryB = User::factory()->create();
    MedicalInformation::factory()->create([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'dob' => '1990-01-01',
        'primary_user_id' => $primaryA->id,
    ]);
    MedicalInformation::factory()->create([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'dob' => '1990-01-01',
        'primary_user_id' => $primaryB->id,
    ]);

    $service = app(MedicalInformationService::class);

    $match = $service->findLinkCandidate([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'suffix' => null,
    ], '1990-01-01');

    expect($match)->toBeNull();
});

it('excludes the caller own record from ambiguity via excludeId', function () {
    $mine = MedicalInformation::factory()->create([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'dob' => '1990-01-01',
    ]);
    $candidate = MedicalInformation::factory()->create([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'dob' => '1990-01-01',
    ]);

    $service = app(MedicalInformationService::class);

    $match = $service->findLinkCandidate([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'suffix' => null,
    ], '1990-01-01', excludeId: $mine->id);

    expect($match?->id)->toBe($candidate->id);
});

it('creates an interim record for registration without linking to anything', function () {
    MedicalInformation::factory()->create([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'dob' => '1990-01-01',
    ]);

    $user = User::factory()->create([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'dob' => '1990-01-01',
    ]);

    $service = app(MedicalInformationService::class);

    $created = $service->createInterim($user, [
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'suffix' => null,
    ], '1990-01-01', Gender::Male->value);

    expect(MedicalInformation::count())->toBe(2)
        ->and($created->users()->count())->toBe(0);
});

it('fans an avatar update out to every linked user', function () {
    $medicalInformation = MedicalInformation::factory()->create();
    $userA = User::factory()->create(['medical_information_id' => $medicalInformation->id]);
    $userB = User::factory()->create(['medical_information_id' => $medicalInformation->id]);

    $service = app(MedicalInformationService::class);
    $service->syncAvatar($medicalInformation, 'avatars/example.jpg', $userA);

    expect($userA->fresh()->profile_photo_path)->toBe('avatars/example.jpg')
        ->and($userB->fresh()->profile_photo_path)->toBe('avatars/example.jpg');
});

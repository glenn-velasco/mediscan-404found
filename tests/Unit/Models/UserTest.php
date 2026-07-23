<?php

use App\Models\User;

it('joins first, middle, last name, and suffix with single spaces', function () {
    $user = User::factory()->make([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'suffix' => 'Jr.',
    ]);

    expect($user->fullname)->toBe('Juan Santos Dela Cruz Jr.');
});

it('skips a null middle name without leaving a double space', function () {
    $user = User::factory()->make([
        'first_name' => 'Juan',
        'middle_name' => null,
        'last_name' => 'Dela Cruz',
        'suffix' => 'Jr.',
    ]);

    expect($user->fullname)->toBe('Juan Dela Cruz Jr.');
});

it('skips a null suffix', function () {
    $user = User::factory()->make([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'suffix' => null,
    ]);

    expect($user->fullname)->toBe('Juan Santos Dela Cruz');
});

it('computes age from dob', function () {
    $user = User::factory()->make([
        'dob' => now()->subYears(30)->toDateString(),
    ]);

    expect($user->age)->toBe(30);
});

it('computes one year less when this year\'s birthday has not occurred yet', function () {
    $user = User::factory()->make([
        'dob' => now()->subYears(30)->addDay()->toDateString(),
    ]);

    expect($user->age)->toBe(29);
});

it('returns null age when dob is null', function () {
    $user = User::factory()->make(['dob' => null]);

    expect($user->age)->toBeNull();
});

it('encrypts name, dob, address, and phone number at rest', function () {
    $user = User::factory()->create([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'suffix' => 'Jr.',
        'dob' => '1990-01-15',
        'address' => '123 Rizal St, Manila',
        'phone_number' => '+639171234567',
    ]);

    $raw = DB::table('users')->where('id', $user->id)->first();

    expect($raw->first_name)->not->toContain('Juan')
        ->and($raw->middle_name)->not->toContain('Santos')
        ->and($raw->last_name)->not->toContain('Dela Cruz')
        ->and($raw->suffix)->not->toContain('Jr.')
        ->and($raw->dob)->not->toContain('1990-01-15')
        ->and($raw->address)->not->toContain('Rizal')
        ->and($raw->phone_number)->not->toContain('+639171234567');

    expect($user->fresh())
        ->first_name->toBe('Juan')
        ->middle_name->toBe('Santos')
        ->last_name->toBe('Dela Cruz')
        ->suffix->toBe('Jr.')
        ->address->toBe('123 Rizal St, Manila')
        ->phone_number->toBe('+639171234567');

    expect($user->fresh()->dob->toDateString())->toBe('1990-01-15');
});

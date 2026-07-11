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

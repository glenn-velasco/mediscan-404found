<?php

use App\Models\User;

it('matches by email', function () {
    $user = User::factory()->create(['email' => 'find-me@example.com']);
    User::factory()->create(['email' => 'other@example.com']);

    expect(User::query()->search('find-me')->pluck('id'))->toEqual(collect([$user->id]));
});

it('matches by first name', function () {
    $user = User::factory()->create(['first_name' => 'Uniquefirstname']);
    User::factory()->create(['first_name' => 'Someoneelse']);

    expect(User::query()->search('Uniquefirstname')->pluck('id'))->toEqual(collect([$user->id]));
});

it('matches by middle name', function () {
    $user = User::factory()->create(['middle_name' => 'Uniquemiddlename']);
    User::factory()->create(['middle_name' => 'Someoneelse']);

    expect(User::query()->search('Uniquemiddlename')->pluck('id'))->toEqual(collect([$user->id]));
});

it('matches by last name', function () {
    $user = User::factory()->create(['last_name' => 'Uniquelastname']);
    User::factory()->create(['last_name' => 'Someoneelse']);

    expect(User::query()->search('Uniquelastname')->pluck('id'))->toEqual(collect([$user->id]));
});

it('matches a full-name term spanning first and last name when middle name is null', function () {
    $user = User::factory()->withoutMiddleName()->create([
        'first_name' => 'Juan',
        'last_name' => 'Delacruz',
    ]);
    User::factory()->create(['first_name' => 'Someone', 'last_name' => 'Else']);

    expect(User::query()->search('Juan Delacruz')->pluck('id'))->toEqual(collect([$user->id]));
});

it('matches a term including the suffix', function () {
    $user = User::factory()->create([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Delacruz',
        'suffix' => 'Jr.',
    ]);

    expect(User::query()->search('Delacruz Jr.')->pluck('id'))->toEqual(collect([$user->id]));
});

it('returns nothing for an unrelated term', function () {
    User::factory()->create([
        'first_name' => 'Juan',
        'last_name' => 'Delacruz',
        'email' => 'juan@example.com',
    ]);

    expect(User::query()->search('completely-unrelated-zzz')->count())->toBe(0);
});

it('filterByAge includes a user exactly at the minimum age', function () {
    $user = User::factory()->ofAge(18)->create();

    expect(User::query()->filterByAge(18)->pluck('id'))->toContain($user->id);
});

it('filterByAge excludes a user one day under the minimum age', function () {
    $user = User::factory()->create(['dob' => now()->subYears(18)->addDay()->toDateString()]);

    expect(User::query()->filterByAge(18)->pluck('id'))->not->toContain($user->id);
});

it('filterByAge includes a user exactly at the maximum age', function () {
    $user = User::factory()->ofAge(65)->create();

    expect(User::query()->filterByAge(18, 65)->pluck('id'))->toContain($user->id);
});

it('filterByAge excludes a user over the maximum age', function () {
    $user = User::factory()->ofAge(66)->create();

    expect(User::query()->filterByAge(18, 65)->pluck('id'))->not->toContain($user->id);
});

it('filterByAge with only a minimum has no upper bound', function () {
    $user = User::factory()->ofAge(90)->create();

    expect(User::query()->filterByAge(18)->pluck('id'))->toContain($user->id);
});

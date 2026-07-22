<?php

namespace Database\Factories;

use App\Models\PendingRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<PendingRegistration> */
class PendingRegistrationFactory extends Factory
{
    protected $model = PendingRegistration::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional()->lastName(),
            'last_name' => fake()->lastName(),
            'suffix' => null,
            'dob' => fake()->date(),
            'gender' => fake()->randomElement(['male', 'female']),
            'address' => fake()->address(),
            'phone_number' => fake()->phoneNumber(),
            'phone_country_code' => 'PH',
        ];
    }
}

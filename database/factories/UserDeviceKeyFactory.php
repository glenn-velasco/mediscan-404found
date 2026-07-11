<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserDeviceKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserDeviceKey>
 */
class UserDeviceKeyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'public_key' => base64_encode(fake()->sha256()),
            'label' => fake()->word().'\'s device',
            'is_active' => true,
            'registered_at' => now(),
            'revoked_at' => null,
        ];
    }
}

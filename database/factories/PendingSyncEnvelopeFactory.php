<?php

namespace Database\Factories;

use App\Enums\EnvelopeType;
use App\Enums\PendingSyncEnvelopeStatus;
use App\Models\PendingSyncEnvelope;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PendingSyncEnvelope>
 */
class PendingSyncEnvelopeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sender_id' => User::factory(),
            'recipient_id' => User::factory(),
            'envelope_type' => fake()->randomElement(EnvelopeType::cases())->value,
            'ciphertext' => base64_encode(fake()->sha256()),
            'status' => PendingSyncEnvelopeStatus::Pending,
            'expires_at' => now()->addDays(7),
            'acknowledged_at' => null,
        ];
    }
}

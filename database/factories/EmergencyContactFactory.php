<?php

namespace Database\Factories;

use App\Enums\RelationToPatient;
use App\Models\EmergencyContact;
use App\Models\MedicalInformation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EmergencyContact>
 */
class EmergencyContactFactory extends Factory
{
    protected $model = EmergencyContact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'medical_information_id' => MedicalInformation::factory(),
            'name' => fake()->name(),
            'relationship' => fake()->randomElement(RelationToPatient::cases())->value,
            'phone_country_code' => '63',
            'phone' => fake()->numerify('9#########'),
            'is_primary' => false,
        ];
    }
}

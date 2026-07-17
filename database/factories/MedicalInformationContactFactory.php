<?php

namespace Database\Factories;

use App\Models\MedicalInformation;
use App\Models\MedicalInformationContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalInformationContact>
 */
class MedicalInformationContactFactory extends Factory
{
    protected $model = MedicalInformationContact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'medical_information_id' => MedicalInformation::factory(),
            'name' => fake()->name(),
            'relationship' => fake()->randomElement(['Parent', 'Spouse', 'Sibling', 'Friend']),
            'phone_number' => fake()->numerify('+63##########'),
            'phone_country_code' => 'PH',
            'is_primary' => false,
        ];
    }
}

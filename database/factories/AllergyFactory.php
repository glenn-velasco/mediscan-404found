<?php

namespace Database\Factories;

use App\Enums\AllergySeverity;
use App\Models\Allergy;
use App\Models\MedicalInformation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Allergy>
 */
class AllergyFactory extends Factory
{
    protected $model = Allergy::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'medical_information_id' => MedicalInformation::factory(),
            'allergen' => fake()->randomElement(['Peanuts', 'Penicillin', 'Shellfish', 'Latex', 'Pollen']),
            'reaction' => fake()->optional()->sentence(),
            'severity' => fake()->randomElement(AllergySeverity::cases())->value,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\MedicalInformation;
use App\Models\Medication;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Medication>
 */
class MedicationFactory extends Factory
{
    protected $model = Medication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'medical_information_id' => MedicalInformation::factory(),
            'name' => fake()->randomElement(['Metformin', 'Lisinopril', 'Albuterol', 'Levothyroxine']),
            'dosage' => fake()->optional()->numerify('###mg'),
            'frequency' => fake()->optional()->randomElement(['Once daily', 'Twice daily', 'As needed']),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\DiagnosisSeverity;
use App\Models\Diagnosis;
use App\Models\MedicalInformation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Diagnosis>
 */
class DiagnosisFactory extends Factory
{
    protected $model = Diagnosis::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'medical_information_id' => MedicalInformation::factory(),
            'condition' => fake()->randomElement(['Type 2 Diabetes', 'Hypertension', 'Asthma', 'Hypothyroidism']),
            'date_of_diagnosis' => fake()->optional()->date(),
            'severity' => fake()->randomElement(DiagnosisSeverity::cases())->value,
        ];
    }
}

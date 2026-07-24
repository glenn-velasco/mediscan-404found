<?php

namespace Database\Factories;

use App\Models\Condition;
use App\Models\MedicalInformation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Condition>
 */
class ConditionFactory extends Factory
{
    protected $model = Condition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'medical_information_id' => MedicalInformation::factory(),
            'description' => fake()->sentence(),
        ];
    }
}

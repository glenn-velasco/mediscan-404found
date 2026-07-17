<?php

namespace Database\Factories;

use App\Models\MedicalInformation;
use App\Models\MedicalInformationTransfusionConsent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalInformationTransfusionConsent>
 */
class MedicalInformationTransfusionConsentFactory extends Factory
{
    protected $model = MedicalInformationTransfusionConsent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'medical_information_id' => MedicalInformation::factory(),
            'consenter_name' => fake()->name(),
            'relationship_to_patient' => fake()->randomElement(['Parent', 'Spouse', 'Sibling']),
            'consented_at' => now(),
        ];
    }
}

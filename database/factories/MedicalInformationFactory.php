<?php

namespace Database\Factories;

use App\Enums\BloodType;
use App\Enums\Gender;
use App\Enums\Religion;
use App\Models\MedicalInformation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalInformation>
 */
class MedicalInformationFactory extends Factory
{
    protected $model = MedicalInformation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional()->lastName(),
            'last_name' => fake()->lastName(),
            'suffix' => null,
            'dob' => fake()->date('Y-m-d', '-18 years'),
            'gender' => fake()->randomElement(Gender::cases())->value,
            'blood_type' => fake()->randomElement(BloodType::cases())->value,
            'religion' => fake()->randomElement(Religion::cases())->value,
            'address' => [
                'province' => fake()->city(),
                'street' => fake()->streetAddress(),
                'unit' => null,
                'country' => 'PH',
                'postal_code' => fake()->postcode(),
                'city' => fake()->city(),
            ],
            'no_blood_transfusion' => false,
            'avatar_path' => null,
        ];
    }
}

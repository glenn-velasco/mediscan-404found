<?php

namespace Database\Factories;

use App\Enums\WorkflowStatus;
use App\Models\MedicalInformation;
use App\Models\MedicalInformationRegistrationMatch;
use App\Models\PendingRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MedicalInformationRegistrationMatch> */
class MedicalInformationRegistrationMatchFactory extends Factory
{
    protected $model = MedicalInformationRegistrationMatch::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'pending_registration_id' => PendingRegistration::factory(),
            'candidate_medical_information_id' => MedicalInformation::factory(),
            'status' => WorkflowStatus::Pending,
        ];
    }
}

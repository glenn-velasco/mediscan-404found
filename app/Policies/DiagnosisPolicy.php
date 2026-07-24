<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Diagnosis;
use App\Models\MedicalInformation;
use App\Models\User;

class DiagnosisPolicy
{
    public function view(User $user, Diagnosis $diagnosis): bool
    {
        return $diagnosis->medicalInformation()->whereHas('users', fn ($q) => $q->whereKey($user->id))->exists();
    }

    /**
     * Only a verified professional linked to the target record may author a diagnosis -
     * a diagnosis is a formal professional identification of a condition, not a
     * patient-self-reported note (unlike allergies/medications/emergency contacts).
     */
    public function create(User $user, MedicalInformation $medicalInformation): bool
    {
        return $user->can(Permission::VerifiedProfessional->value)
            && $medicalInformation->users()->whereKey($user->id)->exists();
    }

    public function update(User $user, Diagnosis $diagnosis): bool
    {
        return $user->can(Permission::VerifiedProfessional->value) && $this->view($user, $diagnosis);
    }

    public function delete(User $user, Diagnosis $diagnosis): bool
    {
        return $this->update($user, $diagnosis);
    }
}

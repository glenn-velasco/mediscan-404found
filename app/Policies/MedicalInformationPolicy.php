<?php

namespace App\Policies;

use App\Models\MedicalInformation;
use App\Models\User;

class MedicalInformationPolicy
{
    public function view(User $user, MedicalInformation $medicalInformation): bool
    {
        return $medicalInformation->users()->whereKey($user->id)->exists();
    }

    public function update(User $user, MedicalInformation $medicalInformation): bool
    {
        return $this->view($user, $medicalInformation);
    }

    public function delete(User $user, MedicalInformation $medicalInformation): bool
    {
        return $this->view($user, $medicalInformation);
    }
}

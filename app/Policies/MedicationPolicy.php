<?php

namespace App\Policies;

use App\Models\Medication;
use App\Models\User;

class MedicationPolicy
{
    public function view(User $user, Medication $medication): bool
    {
        return $medication->medicalInformation()->whereHas('users', fn ($q) => $q->whereKey($user->id))->exists();
    }

    public function update(User $user, Medication $medication): bool
    {
        return $this->view($user, $medication);
    }

    public function delete(User $user, Medication $medication): bool
    {
        return $this->view($user, $medication);
    }
}

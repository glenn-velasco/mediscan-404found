<?php

namespace App\Policies;

use App\Models\EmergencyContact;
use App\Models\User;

class EmergencyContactPolicy
{
    public function view(User $user, EmergencyContact $emergencyContact): bool
    {
        return $emergencyContact->medicalInformation()->whereHas('users', fn ($q) => $q->whereKey($user->id))->exists();
    }

    public function update(User $user, EmergencyContact $emergencyContact): bool
    {
        return $this->view($user, $emergencyContact);
    }

    public function delete(User $user, EmergencyContact $emergencyContact): bool
    {
        return $this->view($user, $emergencyContact);
    }
}

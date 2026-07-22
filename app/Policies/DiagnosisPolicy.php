<?php

namespace App\Policies;

use App\Models\Diagnosis;
use App\Models\User;

class DiagnosisPolicy
{
    public function view(User $user, Diagnosis $diagnosis): bool
    {
        return $diagnosis->medicalInformation()->whereHas('users', fn ($q) => $q->whereKey($user->id))->exists();
    }

    public function update(User $user, Diagnosis $diagnosis): bool
    {
        return $this->view($user, $diagnosis);
    }

    public function delete(User $user, Diagnosis $diagnosis): bool
    {
        return $this->view($user, $diagnosis);
    }
}

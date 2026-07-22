<?php

namespace App\Policies;

use App\Models\Allergy;
use App\Models\User;

class AllergyPolicy
{
    public function view(User $user, Allergy $allergy): bool
    {
        return $allergy->medicalInformation()->whereHas('users', fn ($q) => $q->whereKey($user->id))->exists();
    }

    public function update(User $user, Allergy $allergy): bool
    {
        return $this->view($user, $allergy);
    }

    public function delete(User $user, Allergy $allergy): bool
    {
        return $this->view($user, $allergy);
    }
}

<?php

namespace App\Policies;

use App\Models\Condition;
use App\Models\User;

class ConditionPolicy
{
    public function view(User $user, Condition $condition): bool
    {
        return $condition->medicalInformation()->whereHas('users', fn ($q) => $q->whereKey($user->id))->exists();
    }

    public function update(User $user, Condition $condition): bool
    {
        return $this->view($user, $condition);
    }

    public function delete(User $user, Condition $condition): bool
    {
        return $this->view($user, $condition);
    }
}

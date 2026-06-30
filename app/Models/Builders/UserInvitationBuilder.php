<?php

namespace App\Models\Builders;

use Illuminate\Database\Eloquent\Builder;

class UserInvitationBuilder extends Builder
{
    public function whereToken(string $token): self
    {
        return $this->where('token', $token);
    }
}

<?php

namespace App\Models\Builders;

use App\Models\UserInvitation;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<UserInvitation>
 */
class UserInvitationBuilder extends Builder
{
    public function whereToken(string $token): self
    {
        return $this->where('token', $token);
    }
}

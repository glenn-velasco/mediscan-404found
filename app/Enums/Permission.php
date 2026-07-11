<?php

namespace App\Enums;

use App\Traits\HasEnumHelpers;

enum Permission: string
{
    use HasEnumHelpers;

    case ManageUsers = 'manage users';
    case InviteUserAsAdmin = 'invite user as admin';
    case VerifiedProfessional = 'verified professional';

    public function label(): string
    {
        return match ($this) {
            self::ManageUsers => 'Manage Users',
            self::InviteUserAsAdmin => 'Invite User As Admin',
            self::VerifiedProfessional => 'Verified Professional',
        };
    }
}

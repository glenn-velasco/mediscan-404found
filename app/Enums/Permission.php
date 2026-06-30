<?php

namespace App\Enums;

use App\Traits\HasEnumHelpers;

enum Permission: string
{
    use HasEnumHelpers;

    case ManageUsers = 'manage users';
    case ManageMedicalInformation = 'manage medical information';
    case ManageRecords = 'manage records';
    case ManageAllergies = 'manage allergies';

    case InviteUserAsAdmin = 'invite user as admin';

    public function label(): string
    {
        return match ($this) {
            self::ManageUsers => 'Manage Users',
            self::ManageMedicalInformation => 'Manage Medical Information',
            self::ManageRecords => 'Manage Records',
            self::ManageAllergies => 'Manage Allergies',
            self::InviteUserAsAdmin => 'Invite User As Admin',
        };
    }
}

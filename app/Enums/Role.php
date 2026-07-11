<?php

namespace App\Enums;

use App\Traits\HasEnumHelpers;

enum Role: string
{
    use HasEnumHelpers;

    case Admin = 'admin';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::User => 'User',
        };
    }

    /**
     * @return array<int, Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => [
                Permission::ManageUsers,
                Permission::InviteUserAsAdmin,
                Permission::VerifiedProfessional,
            ],
            self::User => [],
        };
    }
}

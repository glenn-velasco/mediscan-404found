<?php

namespace App\Enums;

use App\Traits\HasEnumHelpers;

enum RelationToPatient: string
{
    use HasEnumHelpers;

    case PARENT = 'parent';
    case SPOUSE = 'spouse';
    case SIBLING = 'sibling';
    case CHILD = 'child';
    case GUARDIAN = 'guardian';
    case FRIEND = 'friend';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PARENT => 'Parent',
            self::SPOUSE => 'Spouse',
            self::SIBLING => 'Sibling',
            self::CHILD => 'Child',
            self::GUARDIAN => 'Guardian',
            self::FRIEND => 'Friend',
            self::OTHER => 'Other',
        };
    }
}

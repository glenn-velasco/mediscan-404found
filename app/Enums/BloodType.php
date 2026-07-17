<?php

namespace App\Enums;

use App\Traits\HasEnumHelpers;

enum BloodType: string
{
    use HasEnumHelpers;

    case A_POSITIVE = 'a_positive';
    case A_NEGATIVE = 'a_negative';
    case B_POSITIVE = 'b_positive';
    case B_NEGATIVE = 'b_negative';
    case AB_POSITIVE = 'ab_positive';
    case AB_NEGATIVE = 'ab_negative';
    case O_POSITIVE = 'o_positive';
    case O_NEGATIVE = 'o_negative';

    public function label(): string
    {
        return match ($this) {
            self::A_POSITIVE => 'A+',
            self::A_NEGATIVE => 'A-',
            self::B_POSITIVE => 'B+',
            self::B_NEGATIVE => 'B-',
            self::AB_POSITIVE => 'AB+',
            self::AB_NEGATIVE => 'AB-',
            self::O_POSITIVE => 'O+',
            self::O_NEGATIVE => 'O-',
        };
    }
}

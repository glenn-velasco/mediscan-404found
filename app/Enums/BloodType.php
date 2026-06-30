<?php

namespace App\Enums;

use App\Traits\HasEnumHelpers;

enum BloodType: string
{
    use HasEnumHelpers;

    case APositive  = 'A+';
    case ANegative  = 'A-';
    case BPositive  = 'B+';
    case BNegative  = 'B-';
    case ABPositive = 'AB+';
    case ABNegative = 'AB-';
    case OPositive  = 'O+';
    case ONegative  = 'O-';
}

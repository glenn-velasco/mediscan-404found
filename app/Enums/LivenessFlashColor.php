<?php

namespace App\Enums;

use App\Traits\HasEnumHelpers;

enum LivenessFlashColor: string
{
    use HasEnumHelpers;

    case Red = 'red';
    case Green = 'green';
    case Blue = 'blue';
}

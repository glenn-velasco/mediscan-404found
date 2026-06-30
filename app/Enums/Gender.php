<?php

namespace App\Enums;

use App\Traits\HasEnumHelpers;

enum Gender: string
{
    use HasEnumHelpers;

    case Male = 'male';
    case Female = 'female';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Male',
            self::Female => 'Female',
        };
    }
}

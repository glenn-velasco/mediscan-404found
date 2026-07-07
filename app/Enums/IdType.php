<?php

namespace App\Enums;

use App\Traits\HasEnumHelpers;

enum IdType: string
{
    use HasEnumHelpers;

    case PhPrc = 'ph_prc';

    public function label(): string
    {
        return match ($this) {
            self::PhPrc => 'Professional Regulation Commission (Philippines)',
        };
    }

    public function issuingCountry(): string
    {
        return match ($this) {
            self::PhPrc => 'PH',
        };
    }
}

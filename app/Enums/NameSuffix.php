<?php

namespace App\Enums;

use App\Traits\HasEnumHelpers;

enum NameSuffix: string
{
    use HasEnumHelpers;

    case JUNIOR = 'junior';
    case SENIOR = 'senior';
    case THE_SECOND = 'the_second';
    case THE_THIRD = 'the_third';
    case THE_FORTH = 'the_forth';
    case THE_FIFTH = 'the_fifth';

    public function label(): string
    {
        return match($this) {
            self::JUNIOR => 'Jr',
            self::SENIOR => 'Sr',
            self::THE_SECOND => 'Ⅱ',
            self::THE_THIRD => 'Ⅲ',
            self::THE_FORTH => 'Ⅳ',
            self::THE_FIFTH => '.Ⅴ'
        };
    }
}

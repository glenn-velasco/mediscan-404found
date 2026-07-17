<?php

namespace App\Enums;

use App\Traits\HasEnumHelpers;

enum Religion: string
{
    use HasEnumHelpers;

    case CATHOLIC = 'catholic';
    case CHRISTIAN = 'christian';
    case ISLAM = 'islam';
    case BUDDHIST = 'buddhist';
    case HINDU = 'hindu';
    case JEWISH = 'jewish';
    case NONE = 'none';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CATHOLIC => 'Catholic',
            self::CHRISTIAN => 'Christian',
            self::ISLAM => 'Islam',
            self::BUDDHIST => 'Buddhist',
            self::HINDU => 'Hindu',
            self::JEWISH => 'Jewish',
            self::NONE => 'None',
            self::OTHER => 'Other',
        };
    }
}

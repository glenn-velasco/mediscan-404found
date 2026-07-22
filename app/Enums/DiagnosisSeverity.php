<?php

namespace App\Enums;

use App\Traits\HasEnumHelpers;

enum DiagnosisSeverity: string
{
    use HasEnumHelpers;

    case Chronic = 'chronic';
    case Ongoing = 'ongoing';
    case Acute = 'acute';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Chronic => 'Chronic',
            self::Ongoing => 'Ongoing',
            self::Acute => 'Acute',
            self::Critical => 'Critical',
        };
    }
}

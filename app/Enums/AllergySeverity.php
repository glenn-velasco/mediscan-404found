<?php

namespace App\Enums;

use App\Traits\HasEnumHelpers;

enum AllergySeverity: string
{
    use HasEnumHelpers;

    case Mild = 'mild';
    case Moderate = 'moderate';
    case Severe = 'severe';
    case LifeThreatening = 'life-threatening';

    public function label(): string
    {
        return match ($this) {
            self::Mild => 'Mild',
            self::Moderate => 'Moderate',
            self::Severe => 'Severe',
            self::LifeThreatening => 'Life-threatening',
        };
    }
}

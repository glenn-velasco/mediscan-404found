<?php

namespace App\Enums;

use App\Traits\HasEnumHelpers;

enum PendingSyncEnvelopeStatus: string
{
    use HasEnumHelpers;

    case Pending = 'pending';
    case Acknowledged = 'acknowledged';
    case Expired = 'expired';
    case Unclaimed = 'unclaimed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Acknowledged => 'Acknowledged',
            self::Expired => 'Expired',
            self::Unclaimed => 'Unclaimed',
        };
    }
}

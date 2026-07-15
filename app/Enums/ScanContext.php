<?php

namespace App\Enums;

use App\Traits\HasEnumHelpers;

enum ScanContext: string
{
    use HasEnumHelpers;

    case QrScan = 'qr_scan';

    public function label(): string
    {
        return match ($this) {
            self::QrScan => 'QR Scan',
        };
    }
}

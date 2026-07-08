<?php

namespace App\Enums;

use App\Traits\HasEnumHelpers;

enum ProfessionalApplicationStatus: string
{
    use HasEnumHelpers;

    case Processing = 'processing';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Denied = 'denied';
    case AutoRejected = 'auto_rejected';

    public function label(): string
    {
        return match ($this) {
            self::Processing => 'Processing',
            self::PendingReview => 'Pending Review',
            self::Approved => 'Approved',
            self::Denied => 'Denied',
            self::AutoRejected => 'Auto-Rejected',
        };
    }
}

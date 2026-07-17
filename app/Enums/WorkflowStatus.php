<?php

namespace App\Enums;

use App\Traits\HasEnumHelpers;

enum WorkflowStatus: string
{
    use HasEnumHelpers;

    case Processing = 'processing';
    case PendingReview = 'pending_review';
    case Pending = 'pending';
    case Approved = 'approved';
    case Denied = 'denied';
    case AutoRejected = 'auto_rejected';
    case Acknowledged = 'acknowledged';
    case Expired = 'expired';
    case Unclaimed = 'unclaimed';

    public function label(): string
    {
        return match ($this) {
            self::Processing => 'Processing',
            self::PendingReview => 'Pending Review',
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Denied => 'Denied',
            self::AutoRejected => 'Auto-Rejected',
            self::Acknowledged => 'Acknowledged',
            self::Expired => 'Expired',
            self::Unclaimed => 'Unclaimed',
        };
    }
}

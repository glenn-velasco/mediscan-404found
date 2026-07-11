<?php

namespace App\Enums;

use App\Traits\HasEnumHelpers;

enum AuditLogType: string
{
    use HasEnumHelpers;

    case Authentication = 'authentication';
    case View = 'view';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Accepted = 'accepted';
    case Denied = 'denied';

    public function label(): string
    {
        return match ($this) {
            self::Authentication => 'Authentication',
            self::View => 'View',
            self::Create => 'Create',
            self::Update => 'Update',
            self::Delete => 'Delete',
            self::Accepted => 'Accepted',
            self::Denied => 'Denied',
        };
    }
}

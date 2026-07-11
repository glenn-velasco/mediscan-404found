<?php

namespace App\Services\Audit;

use App\Enums\AuditLogType;
use App\Models\AuditLog;
use App\Models\User;

class AuditLogger
{
    /**
     * Log an audit event.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        string $action,
        AuditLogType $type,
        ?User $actor = null,
        ?User $subject = null,
        array $metadata = [],
        ?string $channel = null,
    ): AuditLog {
        return AuditLog::create([
            'actor_id' => $actor?->id,
            'subject_id' => $subject?->id,
            'action' => $action,
            'type' => $type,
            'channel' => $channel,
            'metadata' => empty($metadata) ? null : $metadata,
        ]);
    }
}

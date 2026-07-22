<?php

namespace App\Services\Audit;

use App\Enums\AuditLogType;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
        ?string $description = null,
        ?Model $record = null,
        ?string $ipAddress = null,
    ): AuditLog {
        return AuditLog::create([
            'actor_id' => $actor?->id,
            'subject_id' => $subject?->id,
            'record_type' => $record ? $this->recordType($record) : null,
            'record_id' => $record?->getKey(),
            'action' => $action,
            'description' => $description ?? $this->describe($action, $type, $actor, $subject),
            'type' => $type,
            'channel' => $channel,
            'ip_address' => $ipAddress,
            'metadata' => empty($metadata) ? null : $metadata,
        ]);
    }

    /**
     * Short, stable record-type label for `$record` (e.g. "allergy", not the
     * full `App\Models\Allergy` class name) - kept independent of namespace
     * so a future move/rename of the model class doesn't silently orphan
     * historical audit rows from ones logged after the move.
     */
    private function recordType(Model $record): string
    {
        return Str::snake(class_basename($record));
    }

    /**
     * Generate a default human-readable description from the action's type
     * when the caller doesn't provide a more specific one.
     */
    private function describe(string $action, AuditLogType $type, ?User $actor, ?User $subject): string
    {
        $actorName = $actor ? ($actor->fullname ?: $actor->email) : 'System';
        $subjectName = $subject ? ($subject->fullname ?: $subject->email) : null;

        return match ($type) {
            AuditLogType::Accepted => "{$actorName} approved ".($subjectName ?? 'a record'),
            AuditLogType::Denied => "{$actorName} denied ".($subjectName ?? 'a record'),
            AuditLogType::View => $subjectName
                ? "{$actorName} viewed {$subjectName}'s record"
                : "{$actorName} viewed a record",
            AuditLogType::Create => $subjectName
                ? "{$actorName} created a record for {$subjectName}"
                : "{$actorName} created a record",
            AuditLogType::Update => $subjectName
                ? "{$actorName} updated a record for {$subjectName}"
                : "{$actorName} updated a record",
            AuditLogType::Delete => $subjectName
                ? "{$actorName} deleted a record for {$subjectName}"
                : "{$actorName} deleted a record",
            AuditLogType::Authentication => "{$actorName} performed {$action}",
        };
    }
}

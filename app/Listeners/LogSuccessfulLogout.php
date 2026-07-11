<?php

namespace App\Listeners;

use App\Enums\AuditLogType;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\Logout;

class LogSuccessfulLogout
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(Logout $event): void
    {
        /** @var User|null $user */
        $user = $event->user;

        if (! $user) {
            return;
        }

        $this->auditLogger->log(
            action: 'auth.logout',
            type: AuditLogType::Authentication,
            actor: $user,
            subject: $user,
            channel: 'web',
        );
    }
}

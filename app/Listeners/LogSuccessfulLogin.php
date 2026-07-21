<?php

namespace App\Listeners;

use App\Enums\AuditLogType;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(Login $event): void
    {
        /** @var User $user */
        $user = $event->user;

        metric('auth:logins')->hourly()->record();

        $this->auditLogger->log(
            action: 'auth.login',
            type: AuditLogType::Authentication,
            actor: $user,
            subject: $user,
            channel: 'web',
        );
    }
}

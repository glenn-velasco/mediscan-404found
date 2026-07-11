<?php

namespace App\Services\User;

use App\Enums\AuditLogType;
use App\Models\User;
use App\Models\UserDeviceKey;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class DeviceKeyService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Register or rotate a device's public key.
     * Marks any prior active key for this user as inactive.
     */
    public function registerOrRotate(User $user, string $publicKey, ?string $label = null): UserDeviceKey
    {
        return DB::transaction(function () use ($user, $publicKey, $label) {
            UserDeviceKey::where('user_id', $user->id)
                ->where('is_active', true)
                ->update(['is_active' => false, 'revoked_at' => now()]);

            $key = UserDeviceKey::create([
                'user_id' => $user->id,
                'public_key' => $publicKey,
                'label' => $label,
                'is_active' => true,
            ]);

            $this->auditLogger->log(
                action: 'device_key.registered',
                type: AuditLogType::Create,
                actor: $user,
                subject: $user,
                metadata: ['device_label' => $label],
                channel: 'api',
            );

            return $key;
        });
    }

    /**
     * Revoke a device key.
     */
    public function revoke(User $user, UserDeviceKey $key): void
    {
        if ($key->user_id !== $user->id) {
            abort(403);
        }

        DB::transaction(function () use ($user, $key) {
            $key->update([
                'is_active' => false,
                'revoked_at' => now(),
            ]);

            $this->auditLogger->log(
                action: 'device_key.revoked',
                type: AuditLogType::Delete,
                actor: $user,
                subject: $user,
                metadata: ['device_key_id' => $key->id, 'device_label' => $key->label],
                channel: 'api',
            );
        });
    }
}

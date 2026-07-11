<?php

namespace App\Models;

use Database\Factories\UserDeviceKeyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $public_key
 * @property string|null $label
 * @property bool $is_active
 * @property Carbon $registered_at
 * @property Carbon|null $revoked_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['public_key', 'label', 'is_active'])]
class UserDeviceKey extends Model
{
    /** @use HasFactory<UserDeviceKeyFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

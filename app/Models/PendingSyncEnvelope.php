<?php

namespace App\Models;

use App\Enums\PendingSyncEnvelopeStatus;
use Database\Factories\PendingSyncEnvelopeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $sender_id
 * @property int|null $recipient_id
 * @property string $envelope_type
 * @property string $ciphertext
 * @property PendingSyncEnvelopeStatus $status
 * @property Carbon $expires_at
 * @property Carbon|null $acknowledged_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['sender_id', 'recipient_id', 'envelope_type', 'ciphertext', 'status', 'expires_at', 'acknowledged_at'])]
class PendingSyncEnvelope extends Model
{
    /** @use HasFactory<PendingSyncEnvelopeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => PendingSyncEnvelopeStatus::class,
            'expires_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}

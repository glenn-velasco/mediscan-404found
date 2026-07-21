<?php

namespace App\Models;

use App\Enums\AuditLogType;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $actor_id
 * @property int|null $subject_id
 * @property string $action
 * @property string|null $description
 * @property AuditLogType|null $type
 * @property string|null $channel
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 */
#[Fillable(['actor_id', 'subject_id', 'action', 'description', 'type', 'channel', 'metadata'])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'type' => AuditLogType::class,
            'metadata' => 'json',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_id');
    }
}

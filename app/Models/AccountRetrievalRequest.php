<?php

namespace App\Models;

use App\Enums\WorkflowStatus;
use Database\Factories\AccountRetrievalRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $requester_user_id
 * @property string $old_email
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property Carbon $dob
 * @property string $id_photo_path
 * @property string $selfie_path
 * @property string|null $ocr_extracted_data
 * @property float|null $face_match_score
 * @property bool|null $face_match_passed
 * @property string|null $verification_notes
 * @property WorkflowStatus $status
 * @property string|null $rejection_reason
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Carbon|null $expires_at
 */
#[Guarded('id')]
#[Appends(['expires_at'])]
class AccountRetrievalRequest extends Model
{
    /** @use HasFactory<AccountRetrievalRequestFactory> */
    use HasFactory, MassPrunable;

    private const PRUNE_AFTER_DAYS = 5;

    protected function casts(): array
    {
        return [
            'status' => WorkflowStatus::class,
            'dob' => 'date',
            'face_match_score' => 'float',
            'face_match_passed' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function isTerminal(): bool
    {
        return $this->status !== WorkflowStatus::Pending;
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return Attribute<Carbon|null, never> */
    protected function expiresAt(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->created_at?->addDays(self::PRUNE_AFTER_DAYS),
        );
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<=', now()->subDays(self::PRUNE_AFTER_DAYS));
    }
}

<?php

namespace App\Models;

use App\Enums\WorkflowStatus;
use Database\Factories\MedicalInformationRegistrationMatchFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $requester_user_id
 * @property int|null $pending_registration_id
 * @property int $candidate_medical_information_id
 * @property WorkflowStatus $status
 * @property Carbon|null $responded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Carbon|null $expires_at
 */
#[Guarded('id')]
#[Appends(['expires_at'])]
class MedicalInformationRegistrationMatch extends Model
{
    /** @use HasFactory<MedicalInformationRegistrationMatchFactory> */
    use HasFactory;

    /**
     * How long an unanswered match stays pending before
     * `registration-matches:expire-stale` auto-resolves it (blocking the
     * requester the same way an explicit deny would). Keep this in sync
     * with the `--days` default on that command and the signed-link expiry
     * in MedicalInformationRegistrationMatchNotification - all three
     * describe the same 7-day window.
     */
    public const PENDING_DAYS = 7;

    protected function casts(): array
    {
        return [
            'status' => WorkflowStatus::class,
            'responded_at' => 'datetime',
        ];
    }

    public function isTerminal(): bool
    {
        return $this->status !== WorkflowStatus::Pending;
    }

    public function isForPendingRegistration(): bool
    {
        return $this->pending_registration_id !== null;
    }

    /** @return Attribute<Carbon|null, never> */
    protected function expiresAt(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->isTerminal() ? null : $this->created_at?->addDays(self::PENDING_DAYS),
        );
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    /** @return BelongsTo<PendingRegistration, $this> */
    public function pendingRegistration(): BelongsTo
    {
        return $this->belongsTo(PendingRegistration::class);
    }

    /** @return BelongsTo<MedicalInformation, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(MedicalInformation::class, 'candidate_medical_information_id');
    }
}

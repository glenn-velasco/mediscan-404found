<?php

namespace App\Models;

use App\Enums\WorkflowStatus;
use Database\Factories\MedicalInformationRegistrationMatchFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $requester_user_id
 * @property int $candidate_medical_information_id
 * @property WorkflowStatus $status
 * @property Carbon|null $responded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Guarded('id')]
class MedicalInformationRegistrationMatch extends Model
{
    /** @use HasFactory<MedicalInformationRegistrationMatchFactory> */
    use HasFactory;

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

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    /** @return BelongsTo<MedicalInformation, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(MedicalInformation::class, 'candidate_medical_information_id');
    }
}

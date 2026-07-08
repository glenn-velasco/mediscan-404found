<?php

namespace App\Models;

use App\Enums\IdType;
use App\Enums\ProfessionalApplicationStatus;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property IdType $id_type
 * @property string $issuing_country
 * @property string|null $profession
 * @property string|null $specialty
 * @property string|null $license_number
 * @property Carbon|null $license_expiry
 * @property string|null $full_name_on_id
 * @property string $id_photo_path
 * @property string $selfie_path
 * @property array<int, string>|null $selfie_frame_paths
 * @property array<int, array{path: string, color: string}>|null $liveness_flash_frames
 * @property string $coe_path
 * @property string|null $coe_original_filename
 * @property array<string, mixed>|null $ocr_extracted_data
 * @property array<string, mixed>|null $ocr_raw_response
 * @property float|null $face_match_score
 * @property bool|null $face_match_passed
 * @property float|null $liveness_score
 * @property bool|null $liveness_passed
 * @property ProfessionalApplicationStatus $status
 * @property string|null $rejection_reason
 * @property string|null $verification_notes
 * @property string|null $role_granted
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Guarded('id')]
class ProfessionalApplication extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'id_type' => IdType::class,
            'status' => ProfessionalApplicationStatus::class,
            'license_expiry' => 'date',
            'ocr_extracted_data' => 'encrypted:array',
            'ocr_raw_response' => 'encrypted:array',
            'license_number' => 'encrypted',
            'selfie_frame_paths' => 'array',
            'liveness_flash_frames' => 'array',
            'face_match_score' => 'float',
            'face_match_passed' => 'boolean',
            'liveness_score' => 'float',
            'liveness_passed' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            ProfessionalApplicationStatus::Approved,
            ProfessionalApplicationStatus::Denied,
            ProfessionalApplicationStatus::AutoRejected,
        ], true);
    }
}

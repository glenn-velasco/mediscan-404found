<?php

namespace App\Models;

use App\Models\Traits\HasVerifications;
use Database\Factories\ConditionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A patient-authored free-text note on their general health state - distinct
 * from `Diagnosis`, which is the professional-authored formal identification
 * of a specific condition. See docs/DIAGNOSES.md.
 *
 * @property string $id
 * @property int $medical_information_id
 * @property string $description
 * @property array<int, array{user_id: int, name: string, verified_at: string}>|null $verified_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Condition extends Model
{
    /** @use HasFactory<ConditionFactory> */
    use HasFactory, HasVerifications, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    // See Allergy::$fillable for why `id` is included here.
    protected $fillable = ['id', 'medical_information_id', 'description', 'verified_by'];

    protected function casts(): array
    {
        return [
            'description' => 'encrypted',
            'verified_by' => 'array',
        ];
    }

    /**
     * @return BelongsTo<MedicalInformation, $this>
     */
    public function medicalInformation(): BelongsTo
    {
        return $this->belongsTo(MedicalInformation::class);
    }
}

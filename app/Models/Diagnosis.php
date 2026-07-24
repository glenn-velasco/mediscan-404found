<?php

namespace App\Models;

use App\Enums\DiagnosisSeverity;
use App\Models\Traits\HasVerifications;
use Database\Factories\DiagnosisFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $medical_information_id
 * @property int|null $diagnosed_by
 * @property string $condition
 * @property Carbon|null $date_of_diagnosis
 * @property DiagnosisSeverity|null $severity
 * @property array<int, array{user_id: int, name: string, verified_at: string}>|null $verified_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Diagnosis extends Model
{
    /** @use HasFactory<DiagnosisFactory> */
    use HasFactory, HasVerifications, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    // See Allergy::$fillable for why `id` is included here.
    protected $fillable = ['id', 'medical_information_id', 'diagnosed_by', 'condition', 'date_of_diagnosis', 'severity', 'verified_by'];

    protected function casts(): array
    {
        return [
            'condition' => 'encrypted',
            'date_of_diagnosis' => 'date',
            'severity' => DiagnosisSeverity::class,
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function diagnosedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diagnosed_by');
    }
}

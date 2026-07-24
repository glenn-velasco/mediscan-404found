<?php

namespace App\Models;

use App\Models\Traits\HasVerifications;
use Database\Factories\MedicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $medical_information_id
 * @property string $name
 * @property string|null $dosage
 * @property string|null $frequency
 * @property string|null $notes
 * @property array|null $verified_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Medication extends Model
{
    /** @use HasFactory<MedicationFactory> */
    use HasFactory, HasVerifications, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    // See Allergy::$fillable for why `id` is included here.
    protected $fillable = ['id', 'medical_information_id', 'name', 'dosage', 'frequency', 'notes', 'verified_by'];

    protected function casts(): array
    {
        return [
            'name' => 'encrypted',
            'dosage' => 'encrypted',
            'notes' => 'encrypted',
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

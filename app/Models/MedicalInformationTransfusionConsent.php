<?php

namespace App\Models;

use Database\Factories\MedicalInformationTransfusionConsentFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $medical_information_id
 * @property string $consenter_name
 * @property string|null $relationship_to_patient
 * @property Carbon $consented_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Guarded('id')]
class MedicalInformationTransfusionConsent extends Model
{
    /** @use HasFactory<MedicalInformationTransfusionConsentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
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

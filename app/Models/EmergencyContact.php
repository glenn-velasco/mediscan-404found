<?php

namespace App\Models;

use App\Enums\RelationToPatient;
use App\Models\Traits\HasVerifications;
use Database\Factories\EmergencyContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $medical_information_id
 * @property string $name
 * @property RelationToPatient|null $relationship
 * @property string|null $phone_country_code
 * @property string|null $phone
 * @property bool $is_primary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class EmergencyContact extends Model
{
    /** @use HasFactory<EmergencyContactFactory> */
    use HasFactory, HasVerifications, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    // See Allergy::$fillable for why `id` is included here.
    protected $fillable = ['id', 'medical_information_id', 'name', 'relationship', 'phone_country_code', 'phone', 'is_primary', 'verified_by'];

    protected function casts(): array
    {
        return [
            'name' => 'encrypted',
            'relationship' => RelationToPatient::class,
            'phone' => 'encrypted',
            'is_primary' => 'boolean',
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

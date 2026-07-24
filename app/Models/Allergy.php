<?php

namespace App\Models;

use App\Enums\AllergySeverity;
use App\Models\Traits\HasVerifications;
use Database\Factories\AllergyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $medical_information_id
 * @property string $allergen
 * @property string|null $reaction
 * @property AllergySeverity $severity
 * @property array<int, array{user_id: int, name: string, verified_at: string}>|null $verified_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Allergy extends Model
{
    /** @use HasFactory<AllergyFactory> */
    use HasFactory, HasVerifications, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    // Deliberately no #[Guarded] here - `id` is a client-generated UUID
    // (the mobile app's own record identity, see the migration comment) and
    // must be mass-assignable on create, unlike the auto-increment `id` on
    // most other models in this app.
    protected $fillable = ['id', 'medical_information_id', 'allergen', 'reaction', 'severity', 'verified_by'];

    protected function casts(): array
    {
        return [
            'allergen' => 'encrypted',
            'reaction' => 'encrypted',
            'severity' => AllergySeverity::class,
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

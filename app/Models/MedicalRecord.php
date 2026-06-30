<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Guarded('id')]
class MedicalRecord extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'medications' => 'array',
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
     * @return HasMany<MedicalAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(MedicalAttachment::class);
    }
}

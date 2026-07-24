<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Diagnosis;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Diagnosis
 */
class DiagnosisResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'condition' => $this->condition,
            'date_of_diagnosis' => $this->date_of_diagnosis?->toDateString(),
            'severity' => $this->severity?->value,
            'diagnosed_by' => $this->diagnosedBy === null ? null : [
                'id' => $this->diagnosedBy->id,
                'fullname' => $this->diagnosedBy->fullname,
            ],
            'verified_by' => $this->verified_by,
            'verified_count' => $this->getVerifiedCount(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

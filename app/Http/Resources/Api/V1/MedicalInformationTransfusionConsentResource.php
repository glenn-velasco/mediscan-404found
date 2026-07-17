<?php

namespace App\Http\Resources\Api\V1;

use App\Models\MedicalInformationTransfusionConsent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MedicalInformationTransfusionConsent
 */
class MedicalInformationTransfusionConsentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'consenter_name' => $this->consenter_name,
            'relationship_to_patient' => $this->relationship_to_patient,
            'consented_at' => $this->consented_at->toIso8601String(),
        ];
    }
}

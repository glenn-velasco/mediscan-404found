<?php

namespace App\Http\Resources\Api\V1;

use App\Models\MedicalInformation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MedicalInformation
 */
class MedicalInformationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'date_of_birth' => $this->date_of_birth->toDateString(),
            'gender' => $this->gender->value,
            'phone' => $this->phone,
            'address' => $this->address,
            'blood_type' => $this->blood_type,
            'religion' => $this->religion,
            'no_blood_transfusion' => $this->no_blood_transfusion,
            'allergies' => AllergyResource::collection($this->whenLoaded('allergies')),
        ];
    }
}

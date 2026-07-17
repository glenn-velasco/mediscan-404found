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
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'fullname' => $this->fullname,
            'suffix' => $this->suffix,
            'dob' => $this->dob->toDateString(),
            'gender' => $this->gender->value,
            'blood_type' => $this->blood_type?->value,
            'religion' => $this->religion?->value,
            'address' => $this->address,
            'no_blood_transfusion' => $this->no_blood_transfusion,
            'avatar' => $this->avatar,
            'linked_user_count' => $this->users_count ?? $this->users()->count(),
            'contacts' => MedicalInformationContactResource::collection($this->whenLoaded('contacts')),
            'transfusion_consents' => MedicalInformationTransfusionConsentResource::collection($this->whenLoaded('transfusionConsents')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Resources\Api\V1;

use App\Models\MedicalInformationContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MedicalInformationContact
 */
class MedicalInformationContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'relationship' => $this->relationship,
            'phone_number' => $this->phone_number,
            'phone_country_code' => $this->phone_country_code,
            'is_primary' => $this->is_primary,
        ];
    }
}

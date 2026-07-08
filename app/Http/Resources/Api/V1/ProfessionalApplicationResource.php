<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ProfessionalApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProfessionalApplication
 */
class ProfessionalApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'id_type' => $this->id_type->value,
            'issuing_country' => $this->issuing_country,
            'profession' => $this->profession,
            'specialty' => $this->specialty,
            'status' => $this->status->value,
            'rejection_reason' => $this->rejection_reason,
            'role_granted' => $this->role_granted,
            'created_at' => $this->created_at,
        ];
    }
}

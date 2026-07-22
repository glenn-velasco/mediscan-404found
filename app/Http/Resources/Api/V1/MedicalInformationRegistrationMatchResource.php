<?php

namespace App\Http\Resources\Api\V1;

use App\Models\MedicalInformationRegistrationMatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Deliberately sparse - never exposes the requester's or candidate's PII.
 * This mirrors the accept/deny email, which only ever says "someone
 * registered claiming to be linked to your medical record."
 *
 * @mixin MedicalInformationRegistrationMatch
 */
class MedicalInformationRegistrationMatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

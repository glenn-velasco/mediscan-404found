<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Allergy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Allergy
 */
class AllergyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'allergen' => $this->allergen,
            'reaction' => $this->reaction,
            'severity' => $this->severity->value,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'verified_by' => $this->verified_by,
            'verified_by_name' => $this->whenLoaded('verifier', fn () => $this->verifier?->name),
        ];
    }
}

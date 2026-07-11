<?php

namespace App\Http\Resources\Api\V1;

use App\Models\UserDeviceKey;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserDeviceKey
 */
class DeviceKeyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'public_key' => $this->public_key,
            'label' => $this->label,
            'is_active' => $this->is_active,
            'registered_at' => $this->registered_at,
            'revoked_at' => $this->revoked_at,
        ];
    }
}

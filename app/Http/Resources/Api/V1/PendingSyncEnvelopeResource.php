<?php

namespace App\Http\Resources\Api\V1;

use App\Models\PendingSyncEnvelope;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PendingSyncEnvelope
 */
class PendingSyncEnvelopeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sender_id' => $this->sender_id,
            'recipient_id' => $this->recipient_id,
            'envelope_type' => $this->envelope_type,
            'ciphertext' => $this->ciphertext, // client still needs to fetch and decrypt
            'status' => $this->status?->value,
            'expires_at' => $this->expires_at,
            'acknowledged_at' => $this->acknowledged_at,
            'created_at' => $this->created_at,
        ];
    }
}

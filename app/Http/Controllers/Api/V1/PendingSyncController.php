<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\Api\V1\PendingSyncEnvelopeResource;
use App\Models\PendingSyncEnvelope;
use App\Services\Sync\PendingSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Sync
 */
class PendingSyncController extends Controller
{
    public function __construct(private PendingSyncService $pendingSyncService) {}

    /**
     * Get pending sync envelopes.
     *
     * Retrieve all envelopes waiting to be delivered to the authenticated user.
     * The mobile app calls this on startup or when triggered by a broadcast notification.
     * Envelopes are ordered by creation time (oldest first) for consistent application order.
     *
     * @response 200 {"status":200,"message":"Success","data":{"envelopes":[{"id":1,"sender_id":2,"recipient_id":1,"envelope_type":"allergy_verification","ciphertext":"<base64-encoded-sealed-box-ciphertext>","status":"pending","expires_at":"2026-10-08T00:00:00.000000Z","acknowledged_at":null,"created_at":"2026-01-01T00:00:00.000000Z"}]}}
     * @response 403 {"status":403,"message":"Your account has been deactivated.","errors":null}
     */
    public function index(Request $request): JsonResponse
    {
        $envelopes = $this->pendingSyncService->pendingFor($request->user());

        return $this->success([
            'envelopes' => PendingSyncEnvelopeResource::collection($envelopes),
        ]);
    }

    /**
     * Acknowledge receipt of a sync envelope.
     *
     * Called after the mobile app successfully decrypts and merges an envelope.
     * This marks the envelope as acknowledged and logs the receipt for audit purposes.
     *
     * @response 200 {"status":200,"message":"Envelope acknowledged.","data":null}
     * @response 403 {"status":403,"message":"Unauthenticated.","errors":null}
     * @response 404 {"status":404,"message":"Not found.","errors":null}
     */
    public function ack(Request $request, PendingSyncEnvelope $pendingSyncEnvelope): JsonResponse
    {
        $this->pendingSyncService->acknowledge($request->user(), $pendingSyncEnvelope);

        return $this->success(message: 'Envelope acknowledged.');
    }
}

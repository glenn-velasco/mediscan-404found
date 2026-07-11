<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\V1\SubmitSyncEnvelopeRequest;
use App\Http\Resources\Api\V1\PendingSyncEnvelopeResource;
use App\Models\User;
use App\Services\Sync\ProfessionalSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Professional
 */
class ProfessionalSyncController extends Controller
{
    public function __construct(private ProfessionalSyncService $professionalSyncService) {}

    /**
     * Fetch a patient's public key.
     *
     * Retrieve a specific patient's current active public key for offline scenario (scenario 1).
     * Used by a professional's phone when initiating a P2P sync handshake over BLE/WiFi.
     * The professional app encrypts the medical data to this key before sending locally.
     *
     * @response 200 {"status":200,"message":"Success","data":{"public_key":"1234567890abcdefghijklmnopqrstuvwxyzABCDEF12"}}
     * @response 404 {"status":404,"message":"Patient has no registered device key.","errors":null}
     */
    public function publicKey(Request $request, User $patient): JsonResponse
    {
        $publicKey = $this->professionalSyncService->patientPublicKey($patient);

        if (! $publicKey) {
            return $this->error('Patient has no registered device key.', 404);
        }

        return $this->success(['public_key' => $publicKey]);
    }

    /**
     * Submit an encrypted sync envelope for a patient.
     *
     * Called when a professional scans a patient (QR/card) without the patient's device present (scenario 2).
     * The `ciphertext` must already be encrypted client-side using the patient's public key (sealed-box).
     * The server relays this opaque blob to the patient's device when it next comes online.
     *
     * @bodyParam ciphertext string required Base64-encoded sealed-box ciphertext. Example: abc123def456...
     * @bodyParam envelope_type string required Type/category of the payload. One of: allergy_verification, transfusion_witness, general_note. Example: allergy_verification
     *
     * @response 201 {"status":201,"message":"Envelope submitted.","data":{"id":1,"sender_id":2,"recipient_id":3,"envelope_type":"allergy_verification","status":"pending","expires_at":"2026-10-08T00:00:00.000000Z","created_at":"2026-01-01T00:00:00.000000Z"}}
     * @response 422 {"status":422,"message":"The ciphertext field is required.","errors":{"ciphertext":["The ciphertext field is required."]}}
     */
    public function submitEnvelope(SubmitSyncEnvelopeRequest $request, User $patient): JsonResponse
    {
        $envelope = $this->professionalSyncService->submitEnvelope(
            professional: $request->user(),
            patient: $patient,
            ciphertext: $request->validated('ciphertext'),
            envelopeType: $request->validated('envelope_type'),
        );

        return $this->success(new PendingSyncEnvelopeResource($envelope), 'Envelope submitted.', 201);
    }
}

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
     * For verification envelope types (allergy_verification, condition_verification,
     * diagnosis_verification, medication_verification), the server automatically updates
     * the `verified_by` JSON column on the target record.
     *
     * @bodyParam ciphertext string required Base64-encoded sealed-box ciphertext. Example: abc123def456...
     * @bodyParam envelope_type string required Type/category of the payload. One of: allergy_verification, condition_verification, diagnosis_verification, medication_verification, transfusion_witness, general_note, medical_information_update. Example: allergy_verification
     * @bodyParam item_identifier string The identifier for the verified item (e.g., allergen name, condition description). Required for verification envelope types. Example: Penicillin
     * @bodyParam verified boolean Whether the item is being verified (true) or unverified (false). Defaults to true. Example: true
     *
     * @response 201 {"status":201,"message":"Envelope submitted.","data":{"id":1,"sender_id":2,"recipient_id":3,"envelope_type":"allergy_verification","ciphertext":"<base64-encoded-sealed-box-ciphertext>","status":"pending","expires_at":"2026-10-08T00:00:00.000000Z","acknowledged_at":null,"created_at":"2026-01-01T00:00:00.000000Z"}}
     * @response 422 {"status":422,"message":"The ciphertext field is required.","errors":{"ciphertext":["The ciphertext field is required."]}}
     */
    public function submitEnvelope(SubmitSyncEnvelopeRequest $request, User $patient): JsonResponse
    {
        $envelope = $this->professionalSyncService->submitEnvelope(
            professional: $request->user(),
            patient: $patient,
            ciphertext: $request->validated('ciphertext'),
            envelopeType: $request->validated('envelope_type'),
            itemIdentifier: $request->validated('item_identifier'),
            verified: $request->boolean('verified', true),
        );

        return $this->success(new PendingSyncEnvelopeResource($envelope), 'Envelope submitted.', 201);
    }

    /**
     * Get current professional's verification status for a patient.
     *
     * Returns the authenticated professional's verification status for each item type
     * (allergies, conditions, diagnoses, medications). Used by the mobile app to
     * correctly initialize toggle states when opening the manage-info sheet.
     *
     * The response groups verifications by type, with each entry containing:
     * - `item`: The item identifier (e.g., allergen name)
     * - `verified`: Whether the professional has verified this item
     * - `verified_at`: ISO 8601 timestamp of the last verification
     *
     * @response 200 {
     *   "status": 200,
     *   "message": "Success",
     *   "data": {
     *     "allergies": [
     *       {"item": "Penicillin", "verified": true, "verified_at": "2026-07-24T12:00:00+00:00"},
     *       {"item": "Aspirin", "verified": false, "verified_at": "2026-07-24T11:00:00+00:00"}
     *     ],
     *     "conditions": [
     *       {"item": "Asthma", "verified": true, "verified_at": "2026-07-24T10:00:00+00:00"}
     *     ],
     *     "diagnoses": [],
     *     "medications": [
     *       {"item": "Metformin", "verified": true, "verified_at": "2026-07-24T09:00:00+00:00"}
     *     ]
     *   }
     * }
     * @response 403 {"status":403,"message":"Unauthorized.","errors":null}
     * @response 404 {"status":404,"message":"Patient not found.","errors":null}
     */
    public function verifications(Request $request, User $patient): JsonResponse
    {
        $verifications = $this->professionalSyncService->getVerifications(
            $request->user(),
            $patient,
        );

        return $this->success($verifications);
    }

    /**
     * Track a verification directly (without an envelope).
     *
     * Used by the mobile app to update verified_by when a professional
     * toggles verification status locally and syncs to the server.
     *
     * @bodyParam envelope_type string required Type of verification. Example: allergy_verification
     * @bodyParam item_identifier string required The item identifier (e.g., allergen name). Example: Penicillin
     * @bodyParam verified boolean required Whether the item is verified. Example: true
     */
    public function trackVerification(Request $request, User $patient): JsonResponse
    {
        $request->validate([
            'envelope_type' => ['required', 'string'],
            'item_identifier' => ['required', 'string'],
            'verified' => ['required', 'boolean'],
        ]);

        $this->professionalSyncService->trackVerification(
            professional: $request->user(),
            patient: $patient,
            envelopeType: $request->validated('envelope_type'),
            itemIdentifier: $request->validated('item_identifier'),
            verified: $request->boolean('verified'),
        );

        return $this->success(null, 'Verification tracked.', 201);
    }
}

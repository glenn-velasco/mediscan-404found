<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditLogType;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\V1\LogEmergencyQrEventRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * @group Emergency QR Events
 */
class EmergencyQrEventController extends Controller
{
    /**
     * Log an emergency QR "shown" or "scanned" event for usage analytics.
     *
     * Best-effort ping fired by the mobile app whenever a patient's emergency QR is presented
     * or scanned; never blocks the offline read path. Reuses the generic audit_logs table —
     * the backend stores no plaintext medical data.
     *
     * @bodyParam action string required Either "shown" or "scanned". Example: scanned
     * @bodyParam patient_id integer required The patient whose emergency QR this event concerns. Example: 1
     * @bodyParam context string The scanning context (e.g. viewer, professional_submit). Example: professional_submit
     *
     * @response 201 {"status":201,"message":"Logged.","data":null}
     * @response 422 {"status":422,"message":"The action field is required.","errors":{"action":["The action field is required."]}}
     */
    public function store(LogEmergencyQrEventRequest $request, AuditLogger $auditLogger): JsonResponse
    {
        $subject = User::whereKey($request->validated('patient_id'))->first(); // nullable — never 404 an analytics ping

        $auditLogger->log(
            action: 'emergency_qr.'.$request->validated('action'),
            type: AuditLogType::View,
            actor: $request->user(),
            subject: $subject,
            metadata: array_filter(['context' => $request->validated('context')]),
        );

        return $this->success(message: 'Logged.', status: 201);
    }
}

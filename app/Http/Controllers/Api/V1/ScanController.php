<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditLogType;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\V1\LogScanRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * @group Scans
 */
class ScanController extends Controller
{
    /**
     * Log a QR scan event for audit purposes.
     *
     * Records who scanned whom. The authenticated user is the scanner (actor);
     * `scanned_user_id` is the scanned subject. Stored in the `audit_logs` table.
     *
     * @bodyParam scanned_user_id integer required The user whose QR code was scanned. Example: 1
     * @bodyParam context string The scan context (e.g. qr_scan). Example: qr_scan
     *
     * @response 201 {"status":201,"message":"Logged.","data":null}
     * @response 422 {"status":422,"message":"The scanned_user_id field is required.","errors":{"scanned_user_id":["The scanned_user_id field is required."]}}
     */
    public function store(LogScanRequest $request, AuditLogger $auditLogger): JsonResponse
    {
        $subject = User::findOrFail((int) $request->validated('scanned_user_id'));

        $auditLogger->log(
            action: 'qr.scanned',
            type: AuditLogType::View,
            actor: $request->user(),
            subject: $subject,
            metadata: array_filter(['context' => $request->validated('context')]),
            channel: 'api',
        );

        return $this->success(message: 'Logged.', status: 201);
    }
}

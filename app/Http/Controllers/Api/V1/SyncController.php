<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Services\Medical\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * @group Sync
 */
class SyncController extends Controller
{
    public function __construct(private SyncService $syncService) {}

    /**
     * Bulk pull of everything changed (created/updated/soft-deleted) across
     * medical information, allergies, diagnoses, medications, and emergency
     * contacts since `since`, for the authenticated user's own linked
     * record. Omit `since` to pull everything. See docs/SYNC.md.
     *
     * @queryParam since string ISO 8601 timestamp of the last successful sync. Example: 2026-07-22T00:00:00Z
     *
     * @response 200 {"status":200,"message":"Success","data":{"server_time":"2026-07-22T00:00:00+00:00","medical_information":null,"allergies":[],"diagnoses":[],"medications":[],"emergency_contacts":[]}}
     * @response 422 {"status":422,"message":"The since field must be a valid date.","errors":{"since":["The since field must be a valid date."]}}
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['since' => ['sometimes', 'date']]);

        $since = $request->filled('since') ? Carbon::parse($request->string('since')->toString()) : null;

        return $this->success($this->syncService->pull($request->user(), $since));
    }
}

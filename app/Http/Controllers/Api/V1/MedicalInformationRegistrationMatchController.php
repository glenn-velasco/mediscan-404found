<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\WorkflowStatus;
use App\Http\Controllers\Api\Controller;
use App\Http\Resources\Api\V1\MedicalInformationRegistrationMatchResource;
use App\Models\MedicalInformationRegistrationMatch;
use App\Services\Medical\MedicalInformationRegistrationMatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * In-app counterpart to the email-based accept/deny flow in
 * routes/web.php - lets a logged-in primary user decide a match from
 * within the app instead of only from the emailed signed link. Both paths
 * call the same MedicalInformationRegistrationMatchService methods.
 *
 * @group Medical Information Registration Matches
 */
class MedicalInformationRegistrationMatchController extends Controller
{
    public function __construct(private MedicalInformationRegistrationMatchService $registrationMatchService) {}

    /**
     * Lists pending matches awaiting the authenticated user's decision (as
     * a candidate record's primary user).
     *
     * @response 200 {"status":200,"message":"Success","data":[{"id":1,"status":"pending","created_at":"2026-07-23T00:00:00.000000Z"}]}
     */
    public function index(Request $request): JsonResponse
    {
        $matches = MedicalInformationRegistrationMatch::query()
            ->whereHas('candidate', fn ($query) => $query->where('primary_user_id', $request->user()->id))
            ->where('status', WorkflowStatus::Pending)
            ->latest()
            ->get();

        return $this->success(MedicalInformationRegistrationMatchResource::collection($matches));
    }

    /**
     * @response 200 {"status":200,"message":"Request accepted.","data":null}
     * @response 404 {"status":404,"message":"Not found.","errors":null}
     */
    public function accept(Request $request, MedicalInformationRegistrationMatch $registrationMatch): JsonResponse
    {
        abort_unless($request->user()->can('decide', $registrationMatch), 404);

        $this->registrationMatchService->accept($registrationMatch);

        return $this->success(null, 'Request accepted.');
    }

    /**
     * @response 200 {"status":200,"message":"Request denied.","data":null}
     * @response 404 {"status":404,"message":"Not found.","errors":null}
     */
    public function deny(Request $request, MedicalInformationRegistrationMatch $registrationMatch): JsonResponse
    {
        abort_unless($request->user()->can('decide', $registrationMatch), 404);

        $this->registrationMatchService->deny($registrationMatch);

        return $this->success(null, 'Request denied.');
    }
}

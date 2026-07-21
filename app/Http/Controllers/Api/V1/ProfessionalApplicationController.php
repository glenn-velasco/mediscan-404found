<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\SubmitProfessionalApplicationRequest;
use App\Http\Resources\Api\V1\ProfessionalApplicationResource;
use App\Models\ProfessionalApplication;
use App\Services\Kyc\ProfessionalApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Professional Applications
 */
class ProfessionalApplicationController extends Controller
{
    public function __construct(private ProfessionalApplicationService $professionalApplicationService) {}

    /**
     * Submits a professional application for automatic + manual KYC review.
     *
     * The selfie/flash frames are a liveness challenge, not a plain photo
     * upload: `selfie_frames` must be a short burst spanning at least one
     * real blink (eyes open -> closed -> open), and `flash_frames` must be
     * captured while the client's own screen displayed each color in
     * `flash_colors` in turn (e.g. flash red, capture a frame, flash green,
     * capture a frame, ...). There is no face-api.js/browser dependency on
     * the API side - clients are free to implement this capture flow
     * however suits their platform (native camera APIs, a WebView, etc.);
     * the server independently re-verifies both the blink and the color
     * reflection in the uploaded frames, so client-side gating is only a UX
     * nicety, never trusted as-is.
     *
     * @bodyParam id_type string required One of: ph_prc. Example: ph_prc
     * @bodyParam date_of_birth date required The applicant's date of birth. Must be at least 18 years ago. Example: 1990-05-20
     * @bodyParam id_photo file required Photo of the professional ID. jpg/jpeg/png, max 5MB.
     * @bodyParam selfie_frames file[] required Burst of 3-10 selfie frames spanning at least one real blink. jpg/jpeg/png, max 3MB each.
     * @bodyParam flash_frames file[] required One selfie frame captured per entry in flash_colors, in the same order (3-10 frames). jpg/jpeg/png, max 3MB each.
     * @bodyParam flash_colors string[] required One of red/green/blue per entry, same length and order as flash_frames - the color that was on-screen when that frame was captured. Example: ["red","green","blue"]
     *
     * @response 201 {"status":201,"message":"Application submitted.","data":{"id":1,"id_type":"ph_prc","issuing_country":"PH","profession":null,"date_of_birth":"1990-05-20","status":"processing","rejection_reason":null,"role_granted":null,"created_at":"2026-07-06T00:00:00.000000Z"}}
     * @response 422 {"status":422,"message":"You already have a professional application being processed or under review.","errors":null}
     */
    public function store(SubmitProfessionalApplicationRequest $request): JsonResponse
    {
        $application = $this->professionalApplicationService->submit($request->user(), $request->validated());

        return $this->success(new ProfessionalApplicationResource($application), 'Application submitted.', 201);
    }

    /**
     * @response 200 {"status":200,"message":"Success","data":[{"id":1,"id_type":"ph_prc","issuing_country":"PH","profession":"Physician","date_of_birth":"1990-05-20","status":"pending_review","rejection_reason":null,"role_granted":null,"created_at":"2026-07-06T00:00:00.000000Z"}]}
     */
    public function index(Request $request): JsonResponse
    {
        $application = $this->professionalApplicationService->latestFor($request->user());

        return $this->success(ProfessionalApplicationResource::collection($application ? [$application] : []));
    }

    /**
     * @response 200 {"status":200,"message":"Success","data":{"id":1,"id_type":"ph_prc","issuing_country":"PH","profession":"Physician","date_of_birth":"1990-05-20","status":"pending_review","rejection_reason":null,"role_granted":null,"created_at":"2026-07-06T00:00:00.000000Z"}}
     * @response status=404 scenario="Not found or not owned" {"status":404,"message":"Not found.","errors":null}
     */
    public function show(Request $request, ProfessionalApplication $professionalApplication): JsonResponse
    {
        $this->professionalApplicationService->ensureOwnedBy($professionalApplication, $request->user());

        return $this->success(new ProfessionalApplicationResource($professionalApplication));
    }
}

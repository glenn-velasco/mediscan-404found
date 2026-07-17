<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\StoreMedicalInformationRequest;
use App\Http\Requests\UpdateMedicalInformationRequest;
use App\Http\Resources\Api\V1\MedicalInformationResource;
use App\Models\MedicalInformation;
use App\Services\Medical\MedicalInformationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Medical Information
 */
class MedicalInformationController extends Controller
{
    public function __construct(private MedicalInformationService $medicalInformationService) {}

    /**
     * Returns the authenticated user's own linked medical information record, if any.
     *
     * @response 200 {"status":200,"message":"Success","data":{"id":1,"first_name":"Juan","last_name":"Dela Cruz","suffix":null,"dob":"1990-01-01","gender":"male","blood_type":"o_positive","religion":"catholic","address":{"province":"Metro Manila","street":"123 Rizal St","unit":null,"country":"PH","postal_code":"1000","city":"Manila"},"no_blood_transfusion":false,"avatar":null,"linked_user_count":1,"contacts":[],"transfusion_consents":[],"created_at":"2026-07-17T00:00:00.000000Z","updated_at":"2026-07-17T00:00:00.000000Z"}}
     * @response 404 {"status":404,"message":"Not found.","errors":null}
     */
    public function index(Request $request): JsonResponse
    {
        $medicalInformation = $this->medicalInformationService->findForUser($request->user()->id);

        abort_if($medicalInformation === null, 404);

        return $this->success(new MedicalInformationResource($medicalInformation));
    }

    /**
     * Creates a new medical information record and links it to the authenticated user.
     *
     * @bodyParam first_name string required Example: Juan
     * @bodyParam middle_name string Example: Santos
     * @bodyParam last_name string required Example: Dela Cruz
     * @bodyParam suffix string One of: junior, senior, the_second, the_third, the_forth, the_fifth.
     * @bodyParam dob string required Date of birth. Example: 1990-01-01
     * @bodyParam gender string required One of: male, female.
     * @bodyParam blood_type string One of: a_positive, a_negative, b_positive, b_negative, ab_positive, ab_negative, o_positive, o_negative.
     * @bodyParam religion string One of: catholic, christian, islam, buddhist, hindu, jewish, none, other.
     * @bodyParam address.province string
     * @bodyParam address.street string
     * @bodyParam address.unit string
     * @bodyParam address.country string
     * @bodyParam address.postal_code string
     * @bodyParam address.city string
     * @bodyParam no_blood_transfusion boolean
     * @bodyParam contacts array
     * @bodyParam transfusion_consents array
     *
     * @response 201 {"status":201,"message":"Medical information created.","data":{"id":1,"first_name":"Juan","last_name":"Dela Cruz"}}
     */
    public function store(StoreMedicalInformationRequest $request): JsonResponse
    {
        $medicalInformation = $this->medicalInformationService->create($request->validated(), $request->user());

        $request->user()->forceFill(['medical_information_id' => $medicalInformation->id])->save();

        return $this->success(new MedicalInformationResource($medicalInformation), 'Medical information created.', 201);
    }

    /**
     * @response 200 {"status":200,"message":"Success","data":{"id":1,"first_name":"Juan","last_name":"Dela Cruz"}}
     * @response 404 {"status":404,"message":"Not found.","errors":null}
     */
    public function show(Request $request, MedicalInformation $medicalInformation): JsonResponse
    {
        // 404, not the framework's default 403, to match this API's existing
        // "not found or not owned" convention (see ProfessionalApplicationController)
        // - never reveal that a record exists to someone who doesn't own it.
        abort_unless($request->user()->can('view', $medicalInformation), 404);

        return $this->success(new MedicalInformationResource($this->medicalInformationService->find($medicalInformation->id, $request->user())));
    }

    /**
     * @bodyParam first_name string
     * @bodyParam middle_name string
     * @bodyParam last_name string
     * @bodyParam suffix string
     * @bodyParam dob string
     * @bodyParam gender string
     * @bodyParam blood_type string
     * @bodyParam religion string
     * @bodyParam address.province string
     * @bodyParam address.street string
     * @bodyParam address.unit string
     * @bodyParam address.country string
     * @bodyParam address.postal_code string
     * @bodyParam address.city string
     * @bodyParam no_blood_transfusion boolean
     * @bodyParam contacts array
     * @bodyParam transfusion_consents array
     *
     * @response 200 {"status":200,"message":"Medical information updated.","data":{"id":1,"first_name":"Juan","last_name":"Dela Cruz"}}
     * @response 404 {"status":404,"message":"Not found.","errors":null}
     */
    public function update(UpdateMedicalInformationRequest $request, MedicalInformation $medicalInformation): JsonResponse
    {
        abort_unless($request->user()->can('update', $medicalInformation), 404);

        $updated = $this->medicalInformationService->update($medicalInformation, $request->validated(), $request->user());

        return $this->success(new MedicalInformationResource($updated), 'Medical information updated.');
    }

    /**
     * @response 200 {"status":200,"message":"Medical information deleted.","data":null}
     * @response 404 {"status":404,"message":"Not found.","errors":null}
     */
    public function destroy(Request $request, MedicalInformation $medicalInformation): JsonResponse
    {
        abort_unless($request->user()->can('delete', $medicalInformation), 404);

        $this->medicalInformationService->delete($medicalInformation, $request->user());

        return $this->success(null, 'Medical information deleted.');
    }
}

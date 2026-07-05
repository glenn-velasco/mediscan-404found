<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\UpdateMedicalInfoRequest;
use App\Http\Resources\Api\V1\MedicalInformationResource;
use App\Services\User\MedicalInfoService;
use Illuminate\Http\JsonResponse;

/**
 * @group Medical Information
 */
class MedicalInformationController extends Controller
{
    public function __construct(private MedicalInfoService $medicalInfoService) {}

    /**
     * @response status=200 scenario="Saved" {"status":200,"message":"Medical information saved.","data":{"id":1,"full_name":"Jane Doe","date_of_birth":"1995-06-15","gender":"female","phone":"9171234567","address":"123 Rizal St, Manila","blood_type":"O+","religion":"Catholic","no_blood_transfusion":false,"allergies":[{"id":1,"allergen":"Peanuts","reaction":"Hives","severity":"severe"}]}}
     * @response status=200 scenario="Saved, email changed" {"status":200,"message":"Medical information saved. Please verify your new email address.","data":{"id":1,"full_name":"Jane Doe","date_of_birth":"1995-06-15","gender":"female","phone":"9171234567","address":"123 Rizal St, Manila","blood_type":"O+","religion":"Catholic","no_blood_transfusion":false,"allergies":[]}}
     * @response 422 {"status":422,"message":"The first name field is required.","errors":{"first_name":["The first name field is required."],"last_name":["The last name field is required."],"date_of_birth":["The date of birth field is required."],"gender":["The gender field is required."]}}
     */
    public function update(UpdateMedicalInfoRequest $request): JsonResponse
    {
        $user = $request->user();

        $emailChanged = $this->medicalInfoService->update($user, $request->validated());

        $medicalInfo = $user->medicalInformation()->with('allergies')->firstOrFail();

        return $this->success(
            new MedicalInformationResource($medicalInfo),
            $emailChanged
                ? 'Medical information saved. Please verify your new email address.'
                : 'Medical information saved.',
        );
    }
}

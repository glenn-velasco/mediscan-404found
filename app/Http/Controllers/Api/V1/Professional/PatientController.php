<?php

namespace App\Http\Controllers\Api\V1\Professional;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\PatientLookupRequest;
use App\Http\Resources\Api\V1\AllergyResource;
use App\Http\Resources\Api\V1\MedicalInformationResource;
use App\Models\Allergy;
use App\Models\User;
use App\Services\Professional\PatientVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Professional
 *
 * Endpoints for verified professionals to look up a patient's medical
 * information, verify allergies, and witness transfusion decisions.
 * Requires a token with the `verified professional` ability.
 */
class PatientController extends Controller
{
    public function __construct(private PatientVerificationService $service) {}

    /**
     * Look up a patient by exact email.
     *
     * @response 200 {"status":200,"message":"Patient found.","data":{"patient_id":2,"medical_information":{"id":2,"full_name":"Jane Doe","no_blood_transfusion":true,"allergies":[]}}}
     * @response status=404 scenario="Unknown email or no medical information" {"status":404,"message":"Not found.","errors":null}
     */
    public function lookup(PatientLookupRequest $request): JsonResponse
    {
        $patient = $this->service->findPatientByEmail($request->validated('email'));

        $medicalInfo = $this->service->patientMedicalInformation($request->user(), $patient);

        return $this->success([
            'patient_id' => $patient->id,
            'medical_information' => new MedicalInformationResource($medicalInfo),
        ], 'Patient found.');
    }

    /**
     * Get a patient's medical information.
     *
     * @response 200 {"status":200,"message":"Patient retrieved.","data":{"patient_id":2,"medical_information":{"id":2,"full_name":"Jane Doe","no_blood_transfusion":true,"allergies":[]}}}
     * @response status=404 scenario="No medical information on file" {"status":404,"message":"Not found.","errors":null}
     */
    public function show(Request $request, User $patient): JsonResponse
    {
        $medicalInfo = $this->service->patientMedicalInformation($request->user(), $patient);

        return $this->success([
            'patient_id' => $patient->id,
            'medical_information' => new MedicalInformationResource($medicalInfo),
        ], 'Patient retrieved.');
    }

    /**
     * Verify a patient's allergy.
     *
     * @response 200 {"status":200,"message":"Allergy verified.","data":{"id":1,"allergen":"Peanuts","reaction":"Hives","severity":"severe","verified_at":"2026-07-07T12:00:00+00:00","verified_by":3}}
     * @response status=403 scenario="Attesting own record" {"status":403,"message":"Professionals cannot attest their own records.","errors":null}
     */
    public function verifyAllergy(Request $request, Allergy $allergy): JsonResponse
    {
        $allergy = $this->service->verifyAllergy($request->user(), $allergy);

        return $this->success(new AllergyResource($allergy->load('verifier')), 'Allergy verified.');
    }

    /**
     * Witness a patient's transfusion decision.
     *
     * Multiple professionals can witness the same decision; each witness is
     * recorded once with a name snapshot and timestamp.
     *
     * @response 200 {"status":200,"message":"Transfusion decision witnessed.","data":{"id":2,"full_name":"Jane Doe","no_blood_transfusion":true,"transfusion_witnesses":[{"user_id":3,"name":"Dr. Cruz","witnessed_at":"2026-07-08T12:00:00+00:00"}]}}
     * @response status=422 scenario="Patient has not recorded a decision, or already witnessed by this professional" {"status":422,"message":"The patient has not recorded a transfusion decision yet.","errors":null}
     */
    public function witnessTransfusion(Request $request, User $patient): JsonResponse
    {
        $medicalInfo = $patient->medicalInformation;

        abort_if(! $medicalInfo, 404);

        $medicalInfo = $this->service->witnessTransfusionDecision($request->user(), $medicalInfo);

        return $this->success(
            new MedicalInformationResource($medicalInfo),
            'Transfusion decision witnessed.',
        );
    }
}

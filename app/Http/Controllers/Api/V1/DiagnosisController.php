<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\StoreDiagnosisRequest;
use App\Http\Requests\UpdateDiagnosisRequest;
use App\Http\Resources\Api\V1\DiagnosisResource;
use App\Models\Diagnosis;
use App\Models\MedicalInformation;
use App\Services\Medical\DiagnosisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Diagnoses
 */
class DiagnosisController extends Controller
{
    public function __construct(private DiagnosisService $diagnosisService) {}

    /**
     * Lists every diagnosis on the authenticated user's own linked medical information record.
     *
     * @response 200 {"status":200,"message":"Success","data":[{"id":"9b1f1c2e-6b7a-4c3a-9c2e-1a2b3c4d5e6f","condition":"Type 2 Diabetes","date_of_diagnosis":"2020-05-01","severity":"chronic","diagnosed_by":{"id":1,"fullname":"Dr. Jane Doe"},"created_at":"2026-07-22T00:00:00.000000Z","updated_at":"2026-07-22T00:00:00.000000Z"}]}
     */
    public function index(Request $request): JsonResponse
    {
        return $this->success(DiagnosisResource::collection($this->diagnosisService->listForUser($request->user())));
    }

    /**
     * Creates a diagnosis on the given medical information record. Only a verified
     * professional linked to that record may author a diagnosis.
     *
     * @bodyParam id string required Client-generated UUID for this record. Example: 9b1f1c2e-6b7a-4c3a-9c2e-1a2b3c4d5e6f
     * @bodyParam condition string required Example: Type 2 Diabetes
     * @bodyParam date_of_diagnosis string Example: 2020-05-01
     * @bodyParam severity string One of: chronic, ongoing, acute, critical.
     *
     * @response 201 {"status":201,"message":"Diagnosis created.","data":{"id":"9b1f1c2e-6b7a-4c3a-9c2e-1a2b3c4d5e6f","condition":"Type 2 Diabetes","date_of_diagnosis":"2020-05-01","severity":"chronic","diagnosed_by":{"id":1,"fullname":"Dr. Jane Doe"},"created_at":"2026-07-22T00:00:00.000000Z","updated_at":"2026-07-22T00:00:00.000000Z"}}
     * @response 404 {"status":404,"message":"Not found.","errors":null}
     */
    public function store(StoreDiagnosisRequest $request, MedicalInformation $medicalInformation): JsonResponse
    {
        abort_unless($request->user()->can('create', [Diagnosis::class, $medicalInformation]), 404);

        $diagnosis = $this->diagnosisService->createForRecord($request->validated(), $medicalInformation, $request->user());

        return $this->success(new DiagnosisResource($diagnosis), 'Diagnosis created.', 201);
    }

    /**
     * @response 200 {"status":200,"message":"Success","data":{"id":"9b1f1c2e-6b7a-4c3a-9c2e-1a2b3c4d5e6f","condition":"Type 2 Diabetes","date_of_diagnosis":"2020-05-01","severity":"chronic","diagnosed_by":{"id":1,"fullname":"Dr. Jane Doe"},"created_at":"2026-07-22T00:00:00.000000Z","updated_at":"2026-07-22T00:00:00.000000Z"}}
     * @response 404 {"status":404,"message":"Not found.","errors":null}
     */
    public function show(Request $request, Diagnosis $diagnosis): JsonResponse
    {
        abort_unless($request->user()->can('view', $diagnosis), 404);

        return $this->success(new DiagnosisResource($diagnosis));
    }

    /**
     * Updates a diagnosis. Only the verified professional who authored it (or another
     * verified professional linked to the same record) may update it.
     *
     * @bodyParam condition string required Example: Type 2 Diabetes
     * @bodyParam date_of_diagnosis string Example: 2020-05-01
     * @bodyParam severity string One of: chronic, ongoing, acute, critical.
     *
     * @response 200 {"status":200,"message":"Diagnosis updated.","data":{"id":"9b1f1c2e-6b7a-4c3a-9c2e-1a2b3c4d5e6f","condition":"Type 2 Diabetes","date_of_diagnosis":"2020-05-01","severity":"chronic","diagnosed_by":{"id":1,"fullname":"Dr. Jane Doe"},"created_at":"2026-07-22T00:00:00.000000Z","updated_at":"2026-07-22T00:00:00.000000Z"}}
     * @response 404 {"status":404,"message":"Not found.","errors":null}
     */
    public function update(UpdateDiagnosisRequest $request, Diagnosis $diagnosis): JsonResponse
    {
        abort_unless($request->user()->can('update', $diagnosis), 404);

        $updated = $this->diagnosisService->update($diagnosis, $request->validated(), $request->user());

        return $this->success(new DiagnosisResource($updated), 'Diagnosis updated.');
    }

    /**
     * @response 200 {"status":200,"message":"Diagnosis deleted.","data":null}
     * @response 404 {"status":404,"message":"Not found.","errors":null}
     */
    public function destroy(Request $request, Diagnosis $diagnosis): JsonResponse
    {
        abort_unless($request->user()->can('delete', $diagnosis), 404);

        $this->diagnosisService->delete($diagnosis, $request->user());

        return $this->success(null, 'Diagnosis deleted.');
    }
}

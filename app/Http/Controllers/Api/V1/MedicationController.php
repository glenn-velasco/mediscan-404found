<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\StoreMedicationRequest;
use App\Http\Requests\UpdateMedicationRequest;
use App\Http\Resources\Api\V1\MedicationResource;
use App\Models\Medication;
use App\Services\Medical\MedicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Medications
 */
class MedicationController extends Controller
{
    public function __construct(private MedicationService $medicationService) {}

    /**
     * Lists every medication on the authenticated user's own linked medical information record.
     *
     * @response 200 {"status":200,"message":"Success","data":[{"id":"9b1f1c2e-6b7a-4c3a-9c2e-1a2b3c4d5e6f","name":"Metformin","dosage":"500mg","frequency":"Twice daily","notes":null,"created_at":"2026-07-22T00:00:00.000000Z","updated_at":"2026-07-22T00:00:00.000000Z"}]}
     */
    public function index(Request $request): JsonResponse
    {
        return $this->success(MedicationResource::collection($this->medicationService->listForUser($request->user())));
    }

    /**
     * Creates a medication on the authenticated user's own linked medical information record.
     *
     * @bodyParam id string required Client-generated UUID for this record. Example: 9b1f1c2e-6b7a-4c3a-9c2e-1a2b3c4d5e6f
     * @bodyParam name string required Example: Metformin
     * @bodyParam dosage string Example: 500mg
     * @bodyParam frequency string Example: Twice daily
     * @bodyParam notes string
     *
     * @response 201 {"status":201,"message":"Medication created.","data":{"id":"9b1f1c2e-6b7a-4c3a-9c2e-1a2b3c4d5e6f","name":"Metformin","dosage":"500mg","frequency":"Twice daily","notes":null,"created_at":"2026-07-22T00:00:00.000000Z","updated_at":"2026-07-22T00:00:00.000000Z"}}
     * @response 422 {"status":422,"message":"No medical information record linked to this account.","errors":null}
     */
    public function store(StoreMedicationRequest $request): JsonResponse
    {
        $medication = $this->medicationService->create($request->validated(), $request->user());

        return $this->success(new MedicationResource($medication), 'Medication created.', 201);
    }

    /**
     * @response 200 {"status":200,"message":"Success","data":{"id":"9b1f1c2e-6b7a-4c3a-9c2e-1a2b3c4d5e6f","name":"Metformin","dosage":"500mg","frequency":"Twice daily","notes":null,"created_at":"2026-07-22T00:00:00.000000Z","updated_at":"2026-07-22T00:00:00.000000Z"}}
     * @response 404 {"status":404,"message":"Not found.","errors":null}
     */
    public function show(Request $request, Medication $medication): JsonResponse
    {
        abort_unless($request->user()->can('view', $medication), 404);

        return $this->success(new MedicationResource($medication));
    }

    /**
     * @bodyParam name string required Example: Metformin
     * @bodyParam dosage string Example: 500mg
     * @bodyParam frequency string Example: Twice daily
     * @bodyParam notes string
     *
     * @response 200 {"status":200,"message":"Medication updated.","data":{"id":"9b1f1c2e-6b7a-4c3a-9c2e-1a2b3c4d5e6f","name":"Metformin","dosage":"500mg","frequency":"Twice daily","notes":null,"created_at":"2026-07-22T00:00:00.000000Z","updated_at":"2026-07-22T00:00:00.000000Z"}}
     * @response 404 {"status":404,"message":"Not found.","errors":null}
     */
    public function update(UpdateMedicationRequest $request, Medication $medication): JsonResponse
    {
        abort_unless($request->user()->can('update', $medication), 404);

        $updated = $this->medicationService->update($medication, $request->validated(), $request->user());

        return $this->success(new MedicationResource($updated), 'Medication updated.');
    }

    /**
     * @response 200 {"status":200,"message":"Medication deleted.","data":null}
     * @response 404 {"status":404,"message":"Not found.","errors":null}
     */
    public function destroy(Request $request, Medication $medication): JsonResponse
    {
        abort_unless($request->user()->can('delete', $medication), 404);

        $this->medicationService->delete($medication, $request->user());

        return $this->success(null, 'Medication deleted.');
    }
}

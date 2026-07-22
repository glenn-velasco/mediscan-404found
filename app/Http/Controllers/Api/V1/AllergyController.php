<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\StoreAllergyRequest;
use App\Http\Requests\UpdateAllergyRequest;
use App\Http\Resources\Api\V1\AllergyResource;
use App\Models\Allergy;
use App\Services\Medical\AllergyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Allergies
 */
class AllergyController extends Controller
{
    public function __construct(private AllergyService $allergyService) {}

    /**
     * Lists every allergy on the authenticated user's own linked medical information record.
     *
     * @response 200 {"status":200,"message":"Success","data":[{"id":"9b1f1c2e-6b7a-4c3a-9c2e-1a2b3c4d5e6f","allergen":"Peanuts","reaction":"Hives","severity":"severe","created_at":"2026-07-22T00:00:00.000000Z","updated_at":"2026-07-22T00:00:00.000000Z"}]}
     */
    public function index(Request $request): JsonResponse
    {
        return $this->success(AllergyResource::collection($this->allergyService->listForUser($request->user())));
    }

    /**
     * Creates an allergy on the authenticated user's own linked medical information record.
     *
     * @bodyParam id string required Client-generated UUID for this record. Example: 9b1f1c2e-6b7a-4c3a-9c2e-1a2b3c4d5e6f
     * @bodyParam allergen string required Example: Peanuts
     * @bodyParam reaction string Example: Hives
     * @bodyParam severity string required One of: mild, moderate, severe, life-threatening.
     *
     * @response 201 {"status":201,"message":"Allergy created.","data":{"id":"9b1f1c2e-6b7a-4c3a-9c2e-1a2b3c4d5e6f","allergen":"Peanuts","reaction":"Hives","severity":"severe","created_at":"2026-07-22T00:00:00.000000Z","updated_at":"2026-07-22T00:00:00.000000Z"}}
     * @response 422 {"status":422,"message":"No medical information record linked to this account.","errors":null}
     */
    public function store(StoreAllergyRequest $request): JsonResponse
    {
        $allergy = $this->allergyService->create($request->validated(), $request->user());

        return $this->success(new AllergyResource($allergy), 'Allergy created.', 201);
    }

    /**
     * @response 200 {"status":200,"message":"Success","data":{"id":"9b1f1c2e-6b7a-4c3a-9c2e-1a2b3c4d5e6f","allergen":"Peanuts","reaction":"Hives","severity":"severe","created_at":"2026-07-22T00:00:00.000000Z","updated_at":"2026-07-22T00:00:00.000000Z"}}
     * @response 404 {"status":404,"message":"Not found.","errors":null}
     */
    public function show(Request $request, Allergy $allergy): JsonResponse
    {
        abort_unless($request->user()->can('view', $allergy), 404);

        return $this->success(new AllergyResource($allergy));
    }

    /**
     * @bodyParam allergen string required Example: Peanuts
     * @bodyParam reaction string Example: Hives
     * @bodyParam severity string required One of: mild, moderate, severe, life-threatening.
     *
     * @response 200 {"status":200,"message":"Allergy updated.","data":{"id":"9b1f1c2e-6b7a-4c3a-9c2e-1a2b3c4d5e6f","allergen":"Peanuts","reaction":"Hives","severity":"severe","created_at":"2026-07-22T00:00:00.000000Z","updated_at":"2026-07-22T00:00:00.000000Z"}}
     * @response 404 {"status":404,"message":"Not found.","errors":null}
     */
    public function update(UpdateAllergyRequest $request, Allergy $allergy): JsonResponse
    {
        abort_unless($request->user()->can('update', $allergy), 404);

        $updated = $this->allergyService->update($allergy, $request->validated(), $request->user());

        return $this->success(new AllergyResource($updated), 'Allergy updated.');
    }

    /**
     * @response 200 {"status":200,"message":"Allergy deleted.","data":null}
     * @response 404 {"status":404,"message":"Not found.","errors":null}
     */
    public function destroy(Request $request, Allergy $allergy): JsonResponse
    {
        abort_unless($request->user()->can('delete', $allergy), 404);

        $this->allergyService->delete($allergy, $request->user());

        return $this->success(null, 'Allergy deleted.');
    }
}

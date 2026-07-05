<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\StoreAllergyRequest;
use App\Http\Requests\UpdateAllergyRequest;
use App\Http\Resources\Api\V1\AllergyResource;
use App\Models\Allergy;
use App\Services\User\AllergyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Allergies
 */
class AllergyController extends Controller
{
    public function __construct(private AllergyService $allergyService) {}

    /**
     * @response 201 {"status":201,"message":"Allergy added.","data":{"id":1,"allergen":"Peanuts","reaction":"Hives","severity":"severe"}}
     * @response 422 {"status":422,"message":"The allergen field is required.","errors":{"allergen":["The allergen field is required."]}}
     * @response status=404 scenario="No medical information on file" {"status":404,"message":"Not found.","errors":null}
     */
    public function store(StoreAllergyRequest $request): JsonResponse
    {
        $allergy = $this->allergyService->create($request->user(), $request->validated());

        return $this->success(new AllergyResource($allergy), 'Allergy added.', 201);
    }

    /**
     * @response 200 {"status":200,"message":"Allergy updated.","data":{"id":1,"allergen":"Peanuts","reaction":"Hives, swelling","severity":"life-threatening"}}
     * @response 422 {"status":422,"message":"The selected severity is invalid.","errors":{"severity":["The selected severity is invalid."]}}
     * @response status=404 scenario="Not found or not owned" {"status":404,"message":"Not found.","errors":null}
     */
    public function update(UpdateAllergyRequest $request, Allergy $allergy): JsonResponse
    {
        $this->allergyService->update($request->user(), $allergy, $request->validated());

        return $this->success(new AllergyResource($allergy->fresh()), 'Allergy updated.');
    }

    /**
     * @response 200 {"status":200,"message":"Allergy removed.","data":null}
     * @response status=404 scenario="Not found or not owned" {"status":404,"message":"Not found.","errors":null}
     */
    public function destroy(Request $request, Allergy $allergy): JsonResponse
    {
        $this->allergyService->delete($request->user(), $allergy);

        return $this->success(message: 'Allergy removed.');
    }
}

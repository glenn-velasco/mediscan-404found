<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\StoreAllergyRequest;
use App\Http\Resources\Api\V1\AllergyResource;
use App\Models\Allergy;
use App\Services\User\AllergyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AllergyController extends Controller
{
    public function __construct(private AllergyService $allergyService) {}

    public function store(StoreAllergyRequest $request): JsonResponse
    {
        $allergy = $this->allergyService->create($request->user(), $request->validated());

        return $this->success(new AllergyResource($allergy), 'Allergy added.', 201);
    }

    public function destroy(Request $request, Allergy $allergy): JsonResponse
    {
        $this->allergyService->delete($request->user(), $allergy);

        return $this->success(message: 'Allergy removed.');
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\StoreConditionRequest;
use App\Http\Requests\UpdateConditionRequest;
use App\Http\Resources\Api\V1\ConditionResource;
use App\Models\Condition;
use App\Services\Medical\ConditionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Conditions
 */
class ConditionController extends Controller
{
    public function __construct(private ConditionService $conditionService) {}

    /**
     * Lists every condition on the authenticated user's own linked medical information record.
     *
     * @response 200 {"status":200,"message":"Success","data":[{"id":"9b1f1c2e-6b7a-4c3a-9c2e-1a2b3c4d5e6f","description":"Occasional migraines","created_at":"2026-07-22T00:00:00.000000Z","updated_at":"2026-07-22T00:00:00.000000Z"}]}
     */
    public function index(Request $request): JsonResponse
    {
        return $this->success(ConditionResource::collection($this->conditionService->listForUser($request->user())));
    }

    /**
     * Creates a condition on the authenticated user's own linked medical information record.
     *
     * @bodyParam id string required Client-generated UUID for this record. Example: 9b1f1c2e-6b7a-4c3a-9c2e-1a2b3c4d5e6f
     * @bodyParam description string required Example: Occasional migraines
     *
     * @response 201 {"status":201,"message":"Condition created.","data":{"id":"9b1f1c2e-6b7a-4c3a-9c2e-1a2b3c4d5e6f","description":"Occasional migraines","created_at":"2026-07-22T00:00:00.000000Z","updated_at":"2026-07-22T00:00:00.000000Z"}}
     * @response 422 {"status":422,"message":"No medical information record linked to this account.","errors":null}
     */
    public function store(StoreConditionRequest $request): JsonResponse
    {
        $condition = $this->conditionService->create($request->validated(), $request->user());

        return $this->success(new ConditionResource($condition), 'Condition created.', 201);
    }

    /**
     * @response 200 {"status":200,"message":"Success","data":{"id":"9b1f1c2e-6b7a-4c3a-9c2e-1a2b3c4d5e6f","description":"Occasional migraines","created_at":"2026-07-22T00:00:00.000000Z","updated_at":"2026-07-22T00:00:00.000000Z"}}
     * @response 404 {"status":404,"message":"Not found.","errors":null}
     */
    public function show(Request $request, Condition $condition): JsonResponse
    {
        abort_unless($request->user()->can('view', $condition), 404);

        return $this->success(new ConditionResource($condition));
    }

    /**
     * @bodyParam description string required Example: Occasional migraines
     *
     * @response 200 {"status":200,"message":"Condition updated.","data":{"id":"9b1f1c2e-6b7a-4c3a-9c2e-1a2b3c4d5e6f","description":"Occasional migraines","created_at":"2026-07-22T00:00:00.000000Z","updated_at":"2026-07-22T00:00:00.000000Z"}}
     * @response 404 {"status":404,"message":"Not found.","errors":null}
     */
    public function update(UpdateConditionRequest $request, Condition $condition): JsonResponse
    {
        abort_unless($request->user()->can('update', $condition), 404);

        $updated = $this->conditionService->update($condition, $request->validated(), $request->user());

        return $this->success(new ConditionResource($updated), 'Condition updated.');
    }

    /**
     * @response 200 {"status":200,"message":"Condition deleted.","data":null}
     * @response 404 {"status":404,"message":"Not found.","errors":null}
     */
    public function destroy(Request $request, Condition $condition): JsonResponse
    {
        abort_unless($request->user()->can('delete', $condition), 404);

        $this->conditionService->delete($condition, $request->user());

        return $this->success(null, 'Condition deleted.');
    }
}

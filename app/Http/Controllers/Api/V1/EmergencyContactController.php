<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\StoreEmergencyContactRequest;
use App\Http\Requests\UpdateEmergencyContactRequest;
use App\Http\Resources\Api\V1\EmergencyContactResource;
use App\Models\EmergencyContact;
use App\Services\Medical\EmergencyContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Emergency Contacts
 */
class EmergencyContactController extends Controller
{
    public function __construct(private EmergencyContactService $emergencyContactService) {}

    /**
     * Lists every emergency contact on the authenticated user's own linked medical information record.
     *
     * @response 200 {"status":200,"message":"Success","data":[{"id":"9b1f1c2e-6b7a-4c3a-9c2e-1a2b3c4d5e6f","name":"Maria Dela Cruz","relationship":"spouse","phone_country_code":"63","phone":"9171234567","is_primary":true,"created_at":"2026-07-22T00:00:00.000000Z","updated_at":"2026-07-22T00:00:00.000000Z"}]}
     */
    public function index(Request $request): JsonResponse
    {
        return $this->success(EmergencyContactResource::collection($this->emergencyContactService->listForUser($request->user())));
    }

    /**
     * Creates an emergency contact on the authenticated user's own linked medical information record.
     *
     * @bodyParam id string required Client-generated UUID for this record. Example: 9b1f1c2e-6b7a-4c3a-9c2e-1a2b3c4d5e6f
     * @bodyParam name string required Example: Maria Dela Cruz
     * @bodyParam relationship string One of: parent, spouse, sibling, child, guardian, friend, other.
     * @bodyParam phone_country_code string Example: 63
     * @bodyParam phone string Example: 9171234567
     * @bodyParam is_primary boolean
     *
     * @response 201 {"status":201,"message":"Emergency contact created.","data":{"id":"9b1f1c2e-6b7a-4c3a-9c2e-1a2b3c4d5e6f","name":"Maria Dela Cruz","relationship":"spouse","phone_country_code":"63","phone":"9171234567","is_primary":true,"created_at":"2026-07-22T00:00:00.000000Z","updated_at":"2026-07-22T00:00:00.000000Z"}}
     * @response 422 {"status":422,"message":"No medical information record linked to this account.","errors":null}
     */
    public function store(StoreEmergencyContactRequest $request): JsonResponse
    {
        $emergencyContact = $this->emergencyContactService->create($request->validated(), $request->user());

        return $this->success(new EmergencyContactResource($emergencyContact), 'Emergency contact created.', 201);
    }

    /**
     * @response 200 {"status":200,"message":"Success","data":{"id":"9b1f1c2e-6b7a-4c3a-9c2e-1a2b3c4d5e6f","name":"Maria Dela Cruz","relationship":"spouse","phone_country_code":"63","phone":"9171234567","is_primary":true,"created_at":"2026-07-22T00:00:00.000000Z","updated_at":"2026-07-22T00:00:00.000000Z"}}
     * @response 404 {"status":404,"message":"Not found.","errors":null}
     */
    public function show(Request $request, EmergencyContact $emergencyContact): JsonResponse
    {
        abort_unless($request->user()->can('view', $emergencyContact), 404);

        return $this->success(new EmergencyContactResource($emergencyContact));
    }

    /**
     * @bodyParam name string required Example: Maria Dela Cruz
     * @bodyParam relationship string One of: parent, spouse, sibling, child, guardian, friend, other.
     * @bodyParam phone_country_code string Example: 63
     * @bodyParam phone string Example: 9171234567
     * @bodyParam is_primary boolean
     *
     * @response 200 {"status":200,"message":"Emergency contact updated.","data":{"id":"9b1f1c2e-6b7a-4c3a-9c2e-1a2b3c4d5e6f","name":"Maria Dela Cruz","relationship":"spouse","phone_country_code":"63","phone":"9171234567","is_primary":true,"created_at":"2026-07-22T00:00:00.000000Z","updated_at":"2026-07-22T00:00:00.000000Z"}}
     * @response 404 {"status":404,"message":"Not found.","errors":null}
     */
    public function update(UpdateEmergencyContactRequest $request, EmergencyContact $emergencyContact): JsonResponse
    {
        abort_unless($request->user()->can('update', $emergencyContact), 404);

        $updated = $this->emergencyContactService->update($emergencyContact, $request->validated(), $request->user());

        return $this->success(new EmergencyContactResource($updated), 'Emergency contact updated.');
    }

    /**
     * @response 200 {"status":200,"message":"Emergency contact deleted.","data":null}
     * @response 404 {"status":404,"message":"Not found.","errors":null}
     */
    public function destroy(Request $request, EmergencyContact $emergencyContact): JsonResponse
    {
        abort_unless($request->user()->can('delete', $emergencyContact), 404);

        $this->emergencyContactService->delete($emergencyContact, $request->user());

        return $this->success(null, 'Emergency contact deleted.');
    }
}

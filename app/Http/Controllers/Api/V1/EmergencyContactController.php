<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\StoreEmergencyContactRequest;
use App\Http\Requests\UpdateEmergencyContactRequest;
use App\Http\Resources\Api\V1\EmergencyContactResource;
use App\Models\EmergencyContact;
use App\Services\User\EmergencyContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Emergency Contacts
 *
 * Manage emergency contacts for the authenticated user's medical information.
 */
class EmergencyContactController extends Controller
{
    public function __construct(private EmergencyContactService $emergencyContactService) {}

    /**
     * List emergency contacts.
     *
     * Returns a paginated list of emergency contacts for the authenticated user.
     *
     * @response 200 {"status":200,"message":"Success","data":[{"id":1,"name":"John Doe","relationship":"Spouse","phone_country_code":"PH","phone":"9171234567","is_primary":true,"created_at":"2026-07-08T12:00:00+00:00"}]}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $contacts = $this->emergencyContactService->paginate($request->user(), perPage: $request->integer('per_page', 15));

        return EmergencyContactResource::collection($contacts);
    }

    /**
     * Create an emergency contact.
     *
     * @bodyParam name string required The contact's full name. Example: John Doe
     * @bodyParam relationship string The relationship to the user. Example: Spouse
     * @bodyParam phone_country_code string The phone country code. Example: PH
     * @bodyParam phone string The phone number. Example: 9171234567
     * @bodyParam is_primary boolean Whether this is the primary contact. Example: true
     *
     * @response 201 {"status":201,"message":"Emergency contact added.","data":{"id":1,"name":"John Doe","relationship":"Spouse","phone_country_code":"PH","phone":"9171234567","is_primary":true,"created_at":"2026-07-08T12:00:00+00:00"}}
     * @response 422 {"status":422,"message":"The name field is required.","errors":{"name":["The name field is required."]}}
     * @response status=404 scenario="No medical information on file" {"status":404,"message":"Not found.","errors":null}
     */
    public function store(StoreEmergencyContactRequest $request): JsonResponse
    {
        $contact = $this->emergencyContactService->create($request->user(), $request->validated());

        return $this->success(new EmergencyContactResource($contact), 'Emergency contact added.', 201);
    }

    /**
     * Update an emergency contact.
     *
     * @urlParam emergencyContact integer required The ID of the emergency contact.
     *
     * @response 200 {"status":200,"message":"Emergency contact updated.","data":{"id":1,"name":"John Doe","relationship":"Parent","phone_country_code":"PH","phone":"9171234567","is_primary":true,"created_at":"2026-07-08T12:00:00+00:00"}}
     * @response 422 {"status":422,"message":"The name field is required.","errors":{"name":["The name field is required."]}}
     * @response status=404 scenario="Not found or not owned" {"status":404,"message":"Not found.","errors":null}
     */
    public function update(UpdateEmergencyContactRequest $request, EmergencyContact $emergencyContact): JsonResponse
    {
        $this->emergencyContactService->update($request->user(), $emergencyContact, $request->validated());

        return $this->success(new EmergencyContactResource($emergencyContact->fresh()), 'Emergency contact updated.');
    }

    /**
     * Delete an emergency contact.
     *
     * @urlParam emergencyContact integer required The ID of the emergency contact.
     *
     * @response 200 {"status":200,"message":"Emergency contact removed.","data":null}
     * @response status=404 scenario="Not found or not owned" {"status":404,"message":"Not found.","errors":null}
     */
    public function destroy(Request $request, EmergencyContact $emergencyContact): JsonResponse
    {
        $this->emergencyContactService->delete($request->user(), $emergencyContact);

        return $this->success(message: 'Emergency contact removed.');
    }
}

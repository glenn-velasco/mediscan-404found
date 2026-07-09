<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\User\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Authentication
 */
class AuthController extends Controller
{
    public function __construct(private AccountService $accountService) {}

    /**
     * @unauthenticated
     *
     * @response 200 {"status":200,"message":"Success","data":{"token":"1|abcdEFGH12345token","user":{"id":1,"name":"Jane Doe","email":"jane.doe@example.com","email_verified_at":"2026-01-01T00:00:00.000000Z","is_active":true}}}
     * @response status=422 scenario="Invalid credentials" {"status":422,"message":"These credentials do not match our records.","errors":{"email":["These credentials do not match our records."]}}
     * @response status=422 scenario="Account deactivated" {"status":422,"message":"Your account has been deactivated.","errors":{"email":["Your account has been deactivated."]}}
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $token = $this->accountService->login(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('device_name'),
        );

        return $this->success([
            'token' => $token->plainTextToken,
            'user' => new UserResource($token->accessToken->tokenable),
        ]);
    }

    /**
     * @unauthenticated
     *
     * @bodyParam device_name string required Name of the device requesting a token. Example: iPhone 15
     * @bodyParam email string required The user's email address. Example: jane.doe@example.com
     * @bodyParam password string required The account password. Example: Password123!
     * @bodyParam password_confirmation string required Must match password. Example: Password123!
     * @bodyParam first_name string required Example: Jane
     * @bodyParam middle_name string Example: Reyes
     * @bodyParam last_name string required Example: Doe
     * @bodyParam suffix string Example: Jr.
     * @bodyParam date_of_birth string required Date, must be before today. Example: 1995-06-15
     * @bodyParam gender string required One of: male, female. Example: female
     * @bodyParam phone_country_code string Example: PH
     * @bodyParam phone string Example: 9171234567
     * @bodyParam blood_type string One of: A+, A-, B+, B-, AB+, AB-, O+, O-. Example: O+
     * @bodyParam religion string Example: Catholic
     * @bodyParam address string Example: 123 Rizal St, Manila
     * @bodyParam no_blood_transfusion boolean Example: false
     * @bodyParam emergency_contact_name string Example: John Doe
     * @bodyParam emergency_contact_phone_country_code string Example: PH
     * @bodyParam emergency_contact_phone string Example: 9179876543
     * @bodyParam emergency_contact_relationship string Example: Spouse
     *
     * @response 201 {"status":201,"message":"Registered.","data":{"token":"1|abcdEFGH12345token","user":{"id":1,"name":"Jane Doe","email":"jane.doe@example.com","email_verified_at":null,"is_active":true}}}
     * @response 422 {"status":422,"message":"The email field is required.","errors":{"email":["The email field is required."]}}
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate(['device_name' => ['required', 'string']]);

        $token = $this->accountService->register($request->all());

        return $this->success([
            'token' => $token->plainTextToken,
            'user' => new UserResource($token->accessToken->tokenable),
        ], 'Registered.', 201);
    }

    /**
     * @response 200 {"status":200,"message":"Logged out.","data":null}
     * @response 401 {"status":401,"message":"Unauthenticated.","errors":null}
     * @response 403 {"status":403,"message":"Your account has been deactivated.","errors":null}
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(message: 'Logged out.');
    }

    /**
     * @response 200 {"status":200,"message":"Success","data":{"user":{"id":1,"name":"Jane Doe","email":"jane.doe@example.com","email_verified_at":"2026-01-01T00:00:00.000000Z","is_active":true,"medical_information":{"id":1,"full_name":"Jane Doe","date_of_birth":"1995-06-15","gender":"female","phone":"9171234567","address":"123 Rizal St, Manila","blood_type":"O+","religion":"Catholic","no_blood_transfusion":false,"allergies":[{"id":1,"allergen":"Peanuts","reaction":"Hives","severity":"severe"}]}}}}
     * @response 403 {"status":403,"message":"Your account has been deactivated.","errors":null}
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing([
            'medicalInformation.allergies',
            'medicalInformation.emergencyContacts',
        ]);

        return $this->success([
            'user' => new UserResource($user),
        ]);
    }
}

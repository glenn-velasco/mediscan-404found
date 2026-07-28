<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditLogType;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\Audit\AuditLogger;
use App\Services\User\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Authentication
 */
class AuthController extends Controller
{
    public function __construct(
        private AccountService $accountService,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @unauthenticated
     *
     * @bodyParam email string required The user's email address. Example: jane.doe@example.com
     * @bodyParam password string required The account password. Example: Password123!
     * @bodyParam device_name string required Name of the device requesting a token. Example: iPhone 15
     *
     * @response 200 {"status":200,"message":"Success","data":{"token":"1|abcdEFGH12345token","user":{"id":1,"first_name":"Jane","middle_name":null,"last_name":"Doe","suffix":null,"fullname":"Jane Doe","dob":"1990-01-15","age":36,"gender":"female","address":"123 Main St","phone_number":"+639171234567","phone_country_code":"PH","email":"jane.doe@example.com","email_verified_at":"2026-01-01T00:00:00.000000Z","is_active":true,"roles":["User"],"permissions":[]}}}
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

        $user = $token->accessToken->tokenable->load(['roles', 'permissions', 'medicalInformation']);

        return $this->success([
            'token' => $token->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * @unauthenticated
     *
     * @bodyParam device_name string required Name of the device requesting a token. Example: iPhone 15
     * @bodyParam first_name string required Example: Jane
     * @bodyParam middle_name string Example: Marie
     * @bodyParam last_name string required Example: Doe
     * @bodyParam suffix string Example: Jr.
     * @bodyParam dob string required Date of birth in YYYY-MM-DD format. Example: 1990-01-15
     * @bodyParam gender string required Must be one of: male, female. Example: female
     * @bodyParam address string required Example: 123 Main St
     * @bodyParam phone_number string required Valid international phone number. Example: +639171234567
     * @bodyParam phone_country_code string required The ISO 3166-1 alpha-2 country code for the phone number. Example: PH
     * @bodyParam email string required The user's email address. Example: jane.doe@example.com
     * @bodyParam password string required The account password. Example: Password123!
     * @bodyParam password_confirmation string required Must match password. Example: Password123!
     *
     * @response 201 {"status":201,"message":"Registered.","data":{"pending":false,"token":"1|abcdEFGH12345token","user":{"id":1,"first_name":"Jane","middle_name":null,"last_name":"Doe","suffix":null,"fullname":"Jane Doe","dob":"1990-01-15","age":36,"gender":"female","address":"123 Main St","phone_number":"+639171234567","phone_country_code":"PH","email":"jane.doe@example.com","email_verified_at":null,"is_active":true,"roles":["User"],"permissions":[]}}}
     * @response 202 scenario="Name+dob matched an existing record - held pending its primary user's confirmation" {"status":202,"message":"Registration received. We just need to confirm this is you - check your email once it's approved.","data":{"pending":true}}
     * @response 422 {"status":422,"message":"The email field is required.","errors":{"email":["The email field is required."]}}
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate(['device_name' => ['required', 'string']]);

        $outcome = $this->accountService->register($request->all());

        if ($outcome['pending']) {
            return $this->success(
                ['pending' => true],
                "Registration received. We just need to confirm this is you - check your email once it's approved.",
                202,
            );
        }

        $token = $outcome['token'];
        $user = $token->accessToken->tokenable->load(['roles', 'permissions', 'medicalInformation']);

        return $this->success([
            'pending' => false,
            'token' => $token->plainTextToken,
            'user' => new UserResource($user),
        ], 'Registered.', 201);
    }

    /**
     * @response 200 {"status":200,"message":"Logged out.","data":null}
     * @response 401 {"status":401,"message":"Unauthenticated.","errors":null}
     * @response 403 {"status":403,"message":"Your account has been deactivated.","errors":null}
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();

        $this->auditLogger->log(
            action: 'auth.logout',
            type: AuditLogType::Authentication,
            actor: $user,
            subject: $user,
            channel: 'api',
        );

        return $this->success(message: 'Logged out.');
    }

    /**
     * @response 200 {"status":200,"message":"Success","data":{"user":{"id":1,"first_name":"Jane","middle_name":null,"last_name":"Doe","suffix":null,"fullname":"Jane Doe","dob":"1990-01-15","age":36,"gender":"female","address":"123 Main St","phone_number":"+639171234567","phone_country_code":"PH","email":"jane.doe@example.com","email_verified_at":"2026-01-01T00:00:00.000000Z","is_active":true,"roles":["User"],"permissions":[]}}}
     * @response 403 {"status":403,"message":"Your account has been deactivated.","errors":null}
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success([
            'user' => new UserResource($request->user()->load(['roles', 'permissions', 'medicalInformation'])),
        ]);
    }
}

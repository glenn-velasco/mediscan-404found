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
      * @bodyParam name string required Full name. Example: Jane Doe
      * @bodyParam email string required The user's email address. Example: jane.doe@example.com
      * @bodyParam password string required The account password. Example: Password123!
      * @bodyParam password_confirmation string required Must match password. Example: Password123!
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
     * @response 200 {"status":200,"message":"Success","data":{"user":{"id":1,"name":"Jane Doe","email":"jane.doe@example.com","email_verified_at":"2026-01-01T00:00:00.000000Z","is_active":true}}
     * @response 403 {"status":403,"message":"Your account has been deactivated.","errors":null}
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success([
            'user' => new UserResource($request->user()),
        ]);
    }
}

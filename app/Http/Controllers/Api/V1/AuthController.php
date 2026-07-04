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

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(message: 'Logged out.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('medicalInformation.allergies');

        return $this->success([
            'user' => new UserResource($user),
        ]);
    }
}

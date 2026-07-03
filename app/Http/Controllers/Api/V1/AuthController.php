<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\User\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private AccountService $accountService) {}

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

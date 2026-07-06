<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\UpdateEmailRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\User\AccountService;
use Illuminate\Http\JsonResponse;

/**
 * @group Account
 */
class AccountController extends Controller
{
    public function __construct(private AccountService $accountService) {}

    /**
     * @response status=200 scenario="Email updated" {"status":200,"message":"Email updated. Please verify your new address.","data":{"id":1,"name":"Jane Doe","email":"new-email@example.com","email_verified_at":null,"is_active":true}}
     * @response status=200 scenario="No changes made" {"status":200,"message":"No changes made.","data":{"id":1,"name":"Jane Doe","email":"jane.doe@example.com","email_verified_at":"2026-01-01T00:00:00.000000Z","is_active":true}}
     * @response status=422 scenario="Email already taken" {"status":422,"message":"The email has already been taken.","errors":{"email":["The email has already been taken."]}}
     */
    public function updateEmail(UpdateEmailRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->email === $request->validated('email')) {
            return $this->success(new UserResource($user), 'No changes made.');
        }

        $updatedUser = $this->accountService->updateEmail($user, $request->validated('email'), 'api');

        return $this->success(
            new UserResource($updatedUser),
            'Email updated. Please verify your new address.',
        );
    }

    /**
     * @bodyParam password_confirmation string required Must match password. Example: Password123!
     *
     * @response 200 {"status":200,"message":"Password updated.","data":null}
     * @response 422 {"status":422,"message":"The current password field is incorrect.","errors":{"current_password":["The current password field is incorrect."]}}
     */
    public function updatePassword(PasswordUpdateRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update([
            'password' => $request->password,
        ]);

        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return $this->success(message: 'Password updated.');
    }
}

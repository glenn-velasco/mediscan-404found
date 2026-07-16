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
     * @bodyParam email string required The new email address. Example: new-email@example.com
     *
     * @response status=200 scenario="Email updated" {"status":200,"message":"Email updated. Please verify your new address.","data":{"id":1,"first_name":"Jane","middle_name":null,"last_name":"Doe","suffix":null,"fullname":"Jane Doe","dob":"1990-01-15","age":36,"gender":"female","address":"123 Main St","phone_number":"+639171234567","phone_country_code":"PH","email":"new-email@example.com","email_verified_at":null,"is_active":true,"roles":["User"],"permissions":[]}}
     * @response status=200 scenario="No changes made" {"status":200,"message":"No changes made.","data":{"id":1,"first_name":"Jane","middle_name":null,"last_name":"Doe","suffix":null,"fullname":"Jane Doe","dob":"1990-01-15","age":36,"gender":"female","address":"123 Main St","phone_number":"+639171234567","phone_country_code":"PH","email":"jane.doe@example.com","email_verified_at":"2026-01-01T00:00:00.000000Z","is_active":true,"roles":["User"],"permissions":[]}}
     * @response status=422 scenario="Email already taken" {"status":422,"message":"The email has already been taken.","errors":{"email":["The email has already been taken."]}}
     */
    public function updateEmail(UpdateEmailRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->email === $request->validated('email')) {
            return $this->success(new UserResource($user->load(['roles', 'permissions'])), 'No changes made.');
        }

        $updatedUser = $this->accountService->updateEmail($user, $request->validated('email'), 'api');

        return $this->success(
            new UserResource($updatedUser->load(['roles', 'permissions'])),
            'Email updated. Please verify your new address.',
        );
    }

    /**
     * Update the authenticated user's password.
     *
     * Revokes all other access tokens after the change.
     *
     * @bodyParam current_password string required The user's current password. Example: OldPassword123!
     * @bodyParam password string required The new password. Example: NewPassword123!
     * @bodyParam password_confirmation string required Must match password. Example: NewPassword123!
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

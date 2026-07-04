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

    public function updateEmail(UpdateEmailRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->email === $request->validated('email')) {
            return $this->success(new UserResource($user), 'No changes made.');
        }

        $this->accountService->updateEmail($user, $request->validated('email'), 'api');

        return $this->success(
            new UserResource($user->fresh()),
            'Email updated. Please verify your new address.',
        );
    }

    public function updatePassword(PasswordUpdateRequest $request): JsonResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        return $this->success(message: 'Password updated.');
    }
}

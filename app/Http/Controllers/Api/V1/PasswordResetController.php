<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Fortify\ResetUserPassword;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @group Password Reset
 */
class PasswordResetController extends Controller
{
    /**
     * @unauthenticated
     *
     * @bodyParam email string required The user's email address. Example: jane.doe@example.com
     *
     * @response 200 {"status":200,"message":"We have emailed your password reset link.","data":null}
     * @response 422 {"status":422,"message":"We can't find a user with that email address.","errors":{"email":["We can't find a user with that email address."]}}
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return $this->success(message: __($status));
    }

    /**
     * @unauthenticated
     *
     * @bodyParam token string required The password reset token from the email. Example: abc123def456
     * @bodyParam email string required The user's email address. Example: jane.doe@example.com
     * @bodyParam password string required The new password. Example: NewPassword123!
     * @bodyParam password_confirmation string required Must match password. Example: NewPassword123!
     *
     * @response 200 {"status":200,"message":"Your password has been reset.","data":null}
     * @response 422 {"status":422,"message":"This password reset token is invalid.","errors":{"email":["This password reset token is invalid."]}}
     */
    public function reset(ResetPasswordRequest $request, ResetUserPassword $resetter): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request, $resetter) {
                $resetter->reset($user, $request->only('password', 'password_confirmation'));

                $user->forceFill(['remember_token' => Str::random(60)])->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return $this->success(message: __($status));
    }
}

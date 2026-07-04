<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Notifications\Api\VerifyApiEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Email Verification
 */
class EmailVerificationController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->success(message: 'Email already verified.');
        }

        $user->notify(new VerifyApiEmail);

        return $this->success(message: 'Verification link sent.');
    }
}

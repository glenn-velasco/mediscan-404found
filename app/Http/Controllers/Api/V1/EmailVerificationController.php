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
    /**
     * @response status=200 scenario="Verification link sent" {"status":200,"message":"Verification link sent.","data":null}
     * @response status=200 scenario="Already verified" {"status":200,"message":"Email already verified.","data":null}
     */
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

<?php

namespace App\Http\Responses;

use App\Enums\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;

class VerifyEmailResponse implements VerifyEmailResponseContract
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        $user = $request->user();

        return redirect()->intended(
            ($user->hasRole(Role::Admin->value) ? route('admin.dashboard') : route('professional-application.show')).'?verified=1'
        );
    }
}

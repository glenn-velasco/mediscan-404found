<?php

namespace App\Http\Responses;

use App\Enums\Role;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        $user = $request->user();

        return redirect()->intended(
            $user->hasRole(Role::Admin->value)
                ? route('admin.dashboard')
                : route('professional-application.show')
        );
    }
}

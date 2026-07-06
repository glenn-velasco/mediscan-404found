<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Inertia\Inertia;
use Inertia\Response;

class VerifyApiEmailController extends Controller
{
    public function __invoke(int $id, string $hash): Response
    {
        $user = User::findOrFail($id);

        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403, 'Invalid verification link.');

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            event(new Verified($user));
        }

        return Inertia::render('auth/verify-email', ['verified' => true]);
    }
}

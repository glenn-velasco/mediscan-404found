<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Response;

/**
 * @group Email Verification
 *
 * Mobile email verification endpoint. The signed URL from the
 * verification email points here; the server verifies the hash
 * and deep-links the user back into the app.
 */
class MobileVerifyEmailController extends Controller
{
    /**
     * Verify email address (mobile).
     *
     * Marks the user's email as verified and redirects to the
     * native app via the `mediscanmobile://email-verified` deep link.
     * If the email is already verified, the redirect still fires
     * (idempotent).
     *
     * Returns an HTML page with JavaScript redirect because some
     * browsers block `302` redirects to custom schemes.
     *
     * @unauthenticated
     *
     * @urlParam id int required The user's ID. Example: 1
     * @urlParam hash string required SHA-1 hash of the user's email for verification. Example: da39a3ee5e6b4b0d3255bfef95601890afd80709
     *
     * @response 200 HTML page with deep link redirect
     * @response 403 {"message":"Invalid verification link."}
     * @response 404 {"message":"Not found."}
     */
    public function __invoke(int $id, string $hash): Response
    {
        $user = User::findOrFail($id);

        abort_unless(
            hash_equals(sha1($user->getEmailForVerification()), $hash),
            403,
            'Invalid verification link.',
        );

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            event(new Verified($user));
        }

        $deepLink = 'mediscanmobile://email-verified';

        return response(<<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Email verified</title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <style>
                body { font-family: -apple-system, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; background: #f5f5f5; }
                .container { text-align: center; padding: 2rem; }
                .check { font-size: 3rem; margin-bottom: 1rem; }
                h1 { font-size: 1.5rem; margin-bottom: 0.5rem; }
                p { color: #666; margin-bottom: 1.5rem; }
                a { display: inline-block; padding: 0.75rem 1.5rem; background: #0d7377; color: white; text-decoration: none; border-radius: 8px; font-weight: 500; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="check">✓</div>
                <h1>Email verified</h1>
                <p>Opening the app...</p>
                <a href="{$deepLink}">Open MediScan</a>
            </div>
            <script>
                window.location.replace('{$deepLink}');
            </script>
        </body>
        </html>
        HTML)->withHeaders([
            'Content-Type' => 'text/html',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}

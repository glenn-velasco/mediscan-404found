<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MedicalInformationRegistrationMatch;
use App\Services\Medical\MedicalInformationRegistrationMatchService;
use Illuminate\Http\Response;

/**
 * @group Registration Match (Mobile)
 *
 * Mobile deep-link endpoints for registration match accept/deny.
 * The signed URL from the notification email points here; the
 * server resolves the match and deep-links the user back into the
 * app via `mediscanmobile://registration-match/{id}`.
 *
 * Returns an HTML page with JavaScript redirect because some
 * browsers block 302 redirects to custom schemes.
 */
class RegistrationMatchController extends Controller
{
    public function __construct(
        private MedicalInformationRegistrationMatchService $registrationMatchService,
    ) {}

    /**
     * Accept registration match (mobile).
     *
     * Marks the match as accepted, materializes the registrant's
     * account onto the shared medical record, and redirects to the
     * native app. Idempotent if already resolved.
     *
     * @unauthenticated
     */
    public function accept(MedicalInformationRegistrationMatch $registrationMatch): Response
    {
        $this->registrationMatchService->accept($registrationMatch);

        return $this->deepLink($registrationMatch->id, 'accepted');
    }

    /**
     * Deny registration match (mobile).
     *
     * Marks the match as denied, discards the staged registration,
     * and redirects to the native app. Idempotent if already
     * resolved.
     *
     * @unauthenticated
     */
    public function deny(MedicalInformationRegistrationMatch $registrationMatch): Response
    {
        $this->registrationMatchService->deny($registrationMatch);

        return $this->deepLink($registrationMatch->id, 'denied');
    }

    private function deepLink(int $id, string $outcome): Response
    {
        $deepLink = "mediscanmobile://registration-match/{$id}?outcome={$outcome}";

        return response(<<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Registration match {$outcome}</title>
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
                <h1>Registration match {$outcome}</h1>
                <p>Opening the app...</p>
                <a href="{$deepLink}">Continue</a>
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

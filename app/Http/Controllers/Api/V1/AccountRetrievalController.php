<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AccountRetrievalRequest;
use Illuminate\Http\Response;

/**
 * @group Account Retrieval (Mobile)
 *
 * Mobile deep-link endpoint for account retrieval request status.
 * The signed URL from the notification email points here; the
 * server deep-links the user back into the app to view the outcome.
 *
 * Returns an HTML page with JavaScript redirect because some
 * browsers block 302 redirects to custom schemes.
 */
class AccountRetrievalController extends Controller
{
    /**
     * View account retrieval request status (mobile).
     *
     * Redirects to the native app via
     * `mediscanmobile://account-retrieval/{id}?status={status}`.
     * No server-side action — the admin already approved or denied.
     *
     * @unauthenticated
     */
    public function show(AccountRetrievalRequest $accountRetrievalRequest): Response
    {
        $status = $accountRetrievalRequest->status->value;
        $deepLink = "mediscanmobile://account-retrieval/{$accountRetrievalRequest->id}?status={$status}";

        return response(<<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Account retrieval {$status}</title>
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
                <h1>Account retrieval {$status}</h1>
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

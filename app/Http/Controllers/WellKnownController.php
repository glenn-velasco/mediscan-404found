<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Serves the /.well-known files required for Android App Links
 * and iOS Universal Links verification.
 *
 * These files tell the OS that this domain is associated with the
 * MediScan mobile app, enabling "Open with MediScan" prompts when
 * users click verification email links on their phones.
 *
 * @see https://developer.android.com/training/app-links/verify-site-associations
 * @see https://developer.apple.com/documentation/xcode/supporting-associated-domains
 */
class WellKnownController extends Controller
{
    /**
     * Android App Links verification file.
     *
     * Served at /.well-known/assetlinks.json
     * The SHA-256 fingerprint must match the signing certificate used
     * to build the APK/AAB (EAS production keystore).
     */
    public function androidAssetLinks(): JsonResponse
    {
        $packageName = 'com.application.mediscanmobile';
        $sha256Fingerprint = config('services.app_links.android_sha256');

        if (! $sha256Fingerprint) {
            abort(404, 'App Links not configured.');
        }

        return response()->json([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => $packageName,
                'sha256_cert_fingerprints' => [$sha256Fingerprint],
            ],
        ]]);
    }

    /**
     * iOS Universal Links verification file.
     *
     * Served at /.well-known/apple-app-site-association
     * The appID must match the Team ID + Bundle Identifier.
     */
    public function appleAppSiteAssociation(): JsonResponse
    {
        $teamId = config('services.app_links.ios_team_id');

        if (! $teamId) {
            abort(404, 'Universal Links not configured.');
        }

        $appId = "{$teamId}.com.application.mediscanmobile";

        return response()->json([
            'applinks' => [
                'apps' => [],
                'details' => [[
                    'appID' => $appId,
                    'paths' => ['/api/v1/verify-email*'],
                ]],
            ],
        ])->withHeaders([
            'Content-Type' => 'application/json',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}

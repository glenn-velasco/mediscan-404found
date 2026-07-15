<?php

use App\Enums\Permission;
use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceKeyController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\EmergencyQrEventController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\PendingSyncController;
use App\Http\Controllers\Api\V1\ProfessionalApplicationController;
use App\Http\Controllers\Api\V1\ProfessionalSyncController;
use App\Http\Controllers\Api\V1\ScanController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:6,1');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:6,1');

    Route::middleware(['auth:sanctum', 'api.active'])->group(function () {
        Route::post('/broadcasting/auth', fn (Request $request) => Broadcast::auth($request));
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
            ->middleware('throttle:6,1');

        Route::put('/email', [AccountController::class, 'updateEmail'])->middleware('throttle:6,1');
        Route::put('/password', [AccountController::class, 'updatePassword'])->middleware('throttle:6,1');

        // Device key registration for P2P sync handshakes
        Route::post('/device-keys', [DeviceKeyController::class, 'store']);
        Route::delete('/device-keys/{deviceKey}', [DeviceKeyController::class, 'destroy']);

        // Pending sync envelope retrieval and acknowledgment
        Route::get('/pending-sync', [PendingSyncController::class, 'index']);
        Route::post('/pending-sync/{pendingSyncEnvelope}/ack', [PendingSyncController::class, 'ack']);

        // Emergency QR usage analytics (shown/scanned events, logged to audit_logs)
        Route::post('/emergency-qr/events', [EmergencyQrEventController::class, 'store']);

        Route::post('/scans', [ScanController::class, 'store']);

        // Uploading a government ID + biometric selfie is sensitive enough to
        // also require a verified email, unlike the routes above.
        Route::middleware('verified')->group(function () {
            Route::post('/professional-applications', [ProfessionalApplicationController::class, 'store']);
            Route::get('/professional-applications', [ProfessionalApplicationController::class, 'index']);
            Route::get('/professional-applications/{professionalApplication}', [ProfessionalApplicationController::class, 'show']);

            // Professional sync: patient public key lookup and envelope submission
            Route::middleware('abilities:'.Permission::VerifiedProfessional->value)
                ->prefix('professional')->group(function () {
                    Route::get('/patients/{patient}/public-key', [ProfessionalSyncController::class, 'publicKey']);
                    Route::post('/patients/{patient}/envelopes', [ProfessionalSyncController::class, 'submitEnvelope']);
                });
        });
    });
});

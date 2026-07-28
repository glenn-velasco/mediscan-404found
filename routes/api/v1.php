<?php

use App\Enums\Permission;
use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AccountRetrievalController;
use App\Http\Controllers\Api\V1\AccountRetrievalRequestController;
use App\Http\Controllers\Api\V1\AllergyController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ConditionController;
use App\Http\Controllers\Api\V1\DeviceKeyController;
use App\Http\Controllers\Api\V1\DiagnosisController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\EmergencyContactController;
use App\Http\Controllers\Api\V1\EmergencyQrEventController;
use App\Http\Controllers\Api\V1\MedicalInformationController;
use App\Http\Controllers\Api\V1\MedicalInformationRegistrationMatchController;
use App\Http\Controllers\Api\V1\MedicationController;
use App\Http\Controllers\Api\V1\MobileVerifyEmailController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\PendingSyncController;
use App\Http\Controllers\Api\V1\ProfessionalApplicationController;
use App\Http\Controllers\Api\V1\ProfessionalSyncController;
use App\Http\Controllers\Api\V1\RegistrationMatchController;
use App\Http\Controllers\Api\V1\ScanController;
use App\Http\Controllers\Api\V1\SyncController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:6,1');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:6,1');

    Route::get('/verify-email/{id}/{hash}', MobileVerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('api.v1.email.verify');

    Route::middleware(['signed', 'throttle:6,1'])
        ->prefix('registration-matches/{registrationMatch}')
        ->name('api.v1.registration-matches.')
        ->group(function () {
            Route::get('/accept', [RegistrationMatchController::class, 'accept'])->name('accept');
            Route::get('/deny', [RegistrationMatchController::class, 'deny'])->name('deny');
        });

    Route::middleware(['signed', 'throttle:6,1'])
        ->prefix('account-retrieval-requests/{accountRetrievalRequest}')
        ->name('api.v1.account-retrieval.')
        ->group(function () {
            Route::get('/', [AccountRetrievalController::class, 'show'])->name('show');
        });

    Route::post('/account-retrieval-requests', [AccountRetrievalRequestController::class, 'store'])->middleware('throttle:6,1');

    Route::middleware(['auth:sanctum', 'api.active', 'throttle:api'])->group(function () {
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

        Route::get('/medical-information-registration-matches', [MedicalInformationRegistrationMatchController::class, 'index']);
        Route::post('/medical-information-registration-matches/{registrationMatch}/accept', [MedicalInformationRegistrationMatchController::class, 'accept']);
        Route::post('/medical-information-registration-matches/{registrationMatch}/deny', [MedicalInformationRegistrationMatchController::class, 'deny']);

        Route::post('/professional-applications', [ProfessionalApplicationController::class, 'store']);
        Route::get('/professional-applications', [ProfessionalApplicationController::class, 'index']);
        Route::get('/professional-applications/{professionalApplication}', [ProfessionalApplicationController::class, 'show'])->withTrashed();

        Route::get('/medical-information', [MedicalInformationController::class, 'index']);
        Route::post('/medical-information', [MedicalInformationController::class, 'store']);
        Route::get('/medical-information/{medicalInformation}', [MedicalInformationController::class, 'show']);
        Route::put('/medical-information/{medicalInformation}', [MedicalInformationController::class, 'update']);
        Route::delete('/medical-information/{medicalInformation}', [MedicalInformationController::class, 'destroy']);
        Route::post('/medical-information/{medicalInformation}/avatar', [MedicalInformationController::class, 'updateAvatar']);

        Route::apiResource('allergies', AllergyController::class)->parameters(['allergies' => 'allergy']);
        Route::apiResource('conditions', ConditionController::class)->parameters(['conditions' => 'condition']);
        Route::post('/medical-information/{medicalInformation}/diagnoses', [DiagnosisController::class, 'store']);
        Route::apiResource('diagnoses', DiagnosisController::class)->parameters(['diagnoses' => 'diagnosis'])->except(['store']);
        Route::apiResource('medications', MedicationController::class)->parameters(['medications' => 'medication']);
        Route::apiResource('emergency-contacts', EmergencyContactController::class)->parameters(['emergency-contacts' => 'emergencyContact']);

        Route::get('/sync', [SyncController::class, 'index'])->middleware('throttle:sync');

        Route::middleware('abilities:'.Permission::VerifiedProfessional->value)
            ->prefix('professional')->group(function () {
                Route::get('/patients/{patient}/public-key', [ProfessionalSyncController::class, 'publicKey']);
                Route::post('/patients/{patient}/envelopes', [ProfessionalSyncController::class, 'submitEnvelope']);
                Route::get('/patients/{patient}/verifications', [ProfessionalSyncController::class, 'verifications']);
                Route::post('/patients/{patient}/verifications', [ProfessionalSyncController::class, 'trackVerification']);
            });
    });
});

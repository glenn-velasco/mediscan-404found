<?php

use App\Enums\Permission;
use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AllergyController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\MedicalInformationController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\Professional\PatientController as ProfessionalPatientController;
use App\Http\Controllers\Api\V1\ProfessionalApplicationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:6,1');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:6,1');

    Route::middleware(['auth:sanctum', 'api.active'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
            ->middleware('throttle:6,1');

        Route::put('/email', [AccountController::class, 'updateEmail'])->middleware('throttle:6,1');
        Route::put('/password', [AccountController::class, 'updatePassword'])->middleware('throttle:6,1');

        Route::put('/medical-information', [MedicalInformationController::class, 'update']);
        Route::patch('/medical-information/transfusion-consent', [MedicalInformationController::class, 'updateTransfusionConsent']);

        Route::post('/allergies', [AllergyController::class, 'store']);
        Route::patch('/allergies/{allergy}', [AllergyController::class, 'update']);
        Route::delete('/allergies/{allergy}', [AllergyController::class, 'destroy']);

        // Uploading a government ID + biometric selfie is sensitive enough to
        // also require a verified email, unlike the routes above.
        Route::middleware('verified')->group(function () {
            Route::post('/professional-applications', [ProfessionalApplicationController::class, 'store']);
            Route::get('/professional-applications', [ProfessionalApplicationController::class, 'index']);
            Route::get('/professional-applications/{professionalApplication}', [ProfessionalApplicationController::class, 'show']);
        });

        Route::middleware(['verified', 'abilities:'.Permission::VerifiedProfessional->value])
            ->prefix('professional')->group(function () {
                Route::get('/patients', [ProfessionalPatientController::class, 'lookup']);
                Route::get('/patients/{patient}', [ProfessionalPatientController::class, 'show']);
                Route::post('/allergies/{allergy}/verify', [ProfessionalPatientController::class, 'verifyAllergy']);
                Route::post('/patients/{patient}/transfusion-witness', [ProfessionalPatientController::class, 'witnessTransfusion']);
            });
    });
});

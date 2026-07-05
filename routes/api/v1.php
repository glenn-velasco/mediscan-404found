<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AllergyController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\MedicalInformationController;
use App\Http\Controllers\Api\V1\PasswordResetController;
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

        Route::post('/allergies', [AllergyController::class, 'store']);
        Route::patch('/allergies/{allergy}', [AllergyController::class, 'update']);
        Route::delete('/allergies/{allergy}', [AllergyController::class, 'destroy']);
    });
});

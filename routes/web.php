<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\ProfessionalApplicationController as AdminProfessionalApplicationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AllergyController;
use App\Http\Controllers\Auth\AcceptInvitationController;
use App\Http\Controllers\Auth\VerifyApiEmailController;
use App\Http\Controllers\BroadcastingDocsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmergencyContactController;
use App\Http\Controllers\Professional\PatientController;
use App\Http\Controllers\ProfessionalApplicationController;
use App\Http\Middleware\CheckUserActive;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

Route::inertia('/', 'welcome')->name('home');

Route::get('/docs/broadcasting', BroadcastingDocsController::class)
    ->middleware('scribe.docs-access')
    ->name('docs.broadcasting');

Route::middleware('guest')->group(function () {

    Route::get('/invite/{token}', [AcceptInvitationController::class, 'show'])
        ->name('invitation.accept');

    Route::post('/invite/{token}', [AcceptInvitationController::class, 'store'])
        ->name('invitation.store');
});

// Public verification landing page for links sent via the token-based API —
// there is no web session at this point, so the signature is the only guard.
Route::get('/verify-email/{id}/{hash}', VerifyApiEmailController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('email.verify');

// Dashboard and its sub-resources are accessible while unverified — a user
// who just changed their email must be able to return here before re-verifying.
Route::middleware(['auth', CheckUserActive::class])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::patch('/dashboard', [DashboardController::class, 'update'])->name('dashboard.update');
    Route::patch('/transfusion-consent', [DashboardController::class, 'updateTransfusionConsent'])->name('transfusion-consent.update');

    Route::post('/allergies', [AllergyController::class, 'store'])->name('allergies.store');
    Route::patch('/allergies/{allergy}', [AllergyController::class, 'update'])->name('allergies.update');
    Route::delete('/allergies/{allergy}', [AllergyController::class, 'destroy'])->name('allergies.destroy');

    Route::post('/emergency-contacts', [EmergencyContactController::class, 'store'])->name('emergency-contacts.store');
    Route::patch('/emergency-contacts/{emergencyContact}', [EmergencyContactController::class, 'update'])->name('emergency-contacts.update');
    Route::delete('/emergency-contacts/{emergencyContact}', [EmergencyContactController::class, 'destroy'])->name('emergency-contacts.destroy');
});

// Uploading a government ID + biometric selfie is sensitive enough to also
// require a verified email, unlike the dashboard group above.
Route::middleware(['auth', 'verified', CheckUserActive::class])
    ->prefix('professional-application')->name('professional-application.')
    ->group(function () {
        Route::get('/', [ProfessionalApplicationController::class, 'show'])->name('show');
        Route::get('/apply', [ProfessionalApplicationController::class, 'create'])->name('create');
        Route::post('/', [ProfessionalApplicationController::class, 'store'])->name('store');
    });

Route::prefix('professional')->name('professional.')
    ->middleware(['auth', 'verified', CheckUserActive::class, PermissionMiddleware::using(Permission::VerifiedProfessional)])
    ->group(function () {
        Route::get('/patients', [PatientController::class, 'index'])->name('patients.index');
        Route::post('/allergies/{allergy}/verify', [PatientController::class, 'verifyAllergy'])->name('allergies.verify');
        Route::post('/patients/{patient}/transfusion-witness', [PatientController::class, 'witnessTransfusion'])->name('patients.transfusion-witness');
    });

Route::prefix('admin')->name('admin.')
    ->middleware(['auth', 'verified', CheckUserActive::class, RoleMiddleware::using(Role::Admin->value)])
    ->group(function () {
        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');

        Route::resource('users', UserController::class)
            ->only(['index', 'show', 'destroy']);
        Route::patch('users/{user}/role', [UserController::class, 'assignRole'])->name('users.role');
        Route::patch('users/{user}/activation', [UserController::class, 'toggleActivation'])->name('users.activation');

        Route::get('invitations', [InvitationController::class, 'index'])->name('invitations.index');
        Route::get('invitations/create', [InvitationController::class, 'create'])
            ->middleware([PermissionMiddleware::using(Permission::InviteUserAsAdmin)])
            ->name('invitations.create');
        Route::post('invitations', [InvitationController::class, 'store'])->name('invitations.store');
        Route::post('invitations/prune', [InvitationController::class, 'prune'])->name('invitations.prune');
        Route::post('invitations/{invitation}/resend', [InvitationController::class, 'resend'])->name('invitations.resend');
        Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');

        Route::get('professional-applications', [AdminProfessionalApplicationController::class, 'index'])->name('professional-applications.index');
        Route::get('professional-applications/{professionalApplication}', [AdminProfessionalApplicationController::class, 'show'])
            ->withTrashed()->name('professional-applications.show');
        Route::get('professional-applications/{professionalApplication}/file/{type}', [AdminProfessionalApplicationController::class, 'file'])
            ->whereIn('type', ['id-photo', 'selfie', 'coe'])
            ->withTrashed()->name('professional-applications.file');
        Route::patch('professional-applications/{professionalApplication}/approve', [AdminProfessionalApplicationController::class, 'approve'])->name('professional-applications.approve');
        Route::patch('professional-applications/{professionalApplication}/reject', [AdminProfessionalApplicationController::class, 'reject'])->name('professional-applications.reject');
    });

require __DIR__.'/settings.php';

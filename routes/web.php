<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\AccountRetrievalRequestController as AdminAccountRetrievalRequestController;
use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\ProfessionalApplicationController as AdminProfessionalApplicationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AcceptInvitationController;
use App\Http\Controllers\Auth\VerifyApiEmailController;
use App\Http\Controllers\BroadcastingDocsController;
use App\Http\Controllers\MedicalInformationRegistrationMatchController;
use App\Http\Controllers\ProfessionalApplicationController;
use App\Http\Controllers\SeoController;
use App\Http\Middleware\CheckUserActive;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

Route::inertia('/', 'welcome', [
    'seo' => [
        'title' => 'Your medical history, one scan away',
        'description' => 'Your complete medical profile behind a QR code. Any healthcare provider can scan it to instantly see your blood type, allergies, medications, and emergency contacts.',
        'path' => '/',
        'image' => '/apple-touch-icon.png',
    ],
])->name('home');

Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');

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

// Signed, no-login landing links emailed to a record's primary user when a
// new registration's name+dob matches their record. The primary accepts or
// denies without ever creating a session - same "signature is the only
// guard" shape as the verify-email route above.
Route::middleware(['signed', 'throttle:6,1'])
    ->prefix('medical-information-registration-matches/{registrationMatch}')
    ->name('medical-information-registration-matches.')
    ->group(function () {
        Route::get('/accept', [MedicalInformationRegistrationMatchController::class, 'accept'])->name('accept');
        Route::get('/deny', [MedicalInformationRegistrationMatchController::class, 'deny'])->name('deny');
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

Route::prefix('admin')->name('admin.')
    ->middleware(['auth', 'verified', CheckUserActive::class, RoleMiddleware::using(Role::Admin->value)])
    ->group(function () {
        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');

        Route::resource('users', UserController::class)
            ->only(['index', 'show', 'destroy']);
        Route::patch('users/{user}/role', [UserController::class, 'assignRole'])->name('users.role');
        Route::patch('users/{user}/activation', [UserController::class, 'toggleActivation'])->name('users.activation');

        Route::get('invitations', [InvitationController::class, 'index'])->name('invitations.index');
        Route::post('invitations', [InvitationController::class, 'store'])
            ->middleware([PermissionMiddleware::using(Permission::InviteUserAsAdmin)])
            ->name('invitations.store');
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

        Route::get('account-retrieval-requests', [AdminAccountRetrievalRequestController::class, 'index'])->name('account-retrieval-requests.index');
        Route::get('account-retrieval-requests/{accountRetrievalRequest}', [AdminAccountRetrievalRequestController::class, 'show'])->name('account-retrieval-requests.show');
        Route::get('account-retrieval-requests/{accountRetrievalRequest}/file/{type}', [AdminAccountRetrievalRequestController::class, 'file'])
            ->whereIn('type', ['id-photo', 'selfie'])
            ->name('account-retrieval-requests.file');
        Route::patch('account-retrieval-requests/{accountRetrievalRequest}/approve', [AdminAccountRetrievalRequestController::class, 'approve'])->name('account-retrieval-requests.approve');
        Route::patch('account-retrieval-requests/{accountRetrievalRequest}/deny', [AdminAccountRetrievalRequestController::class, 'deny'])->name('account-retrieval-requests.deny');
    });

require __DIR__.'/settings.php';

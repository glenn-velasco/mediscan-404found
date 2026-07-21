<?php

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role;
use App\Events\ProfessionalApplicationStatusChanged;
use App\Models\ProfessionalApplication;
use App\Models\User;
use App\Notifications\ProfessionalApplicationApprovedNotification;
use App\Notifications\ProfessionalApplicationDeniedNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Storage::fake('s3');
    Storage::fake('public');

    $this->admin = function (): User {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        return $admin;
    };

    $this->pendingApplication = function (User $user, array $overrides = []): ProfessionalApplication {
        return $user->professionalApplications()->create(array_merge([
            'id_type' => 'ph_prc',
            'issuing_country' => 'PH',
            'profession' => 'Physician',
            'license_number' => '123456',
            'id_photo_path' => 'fixtures/id.jpg',
            'selfie_path' => 'fixtures/selfie.jpg',
            'status' => 'pending_review',
        ], $overrides));
    };
});

it('non admin cannot view the professional applications list', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::User->value);

    $this->actingAs($user)
        ->get(route('admin.professional-applications.index'))
        ->assertForbidden();
});

it('admin can list and filter applications by status', function () {
    $admin = ($this->admin)();
    $applicant = User::factory()->create([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Delacruz',
        'suffix' => null,
    ]);
    ($this->pendingApplication)($applicant);

    $this->actingAs($admin)
        ->get(route('admin.professional-applications.index', ['status' => 'pending_review']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/professional-applications/index')
            ->where('applications.data.0.applicant.name', 'Juan Santos Delacruz')
        );
});

it('admin can stream each evidence file for an application', function () {
    $admin = ($this->admin)();
    $applicant = User::factory()->create();

    Storage::disk('s3')->put('fixtures/id.jpg', 'id-bytes');
    Storage::disk('s3')->put('fixtures/selfie.jpg', 'selfie-bytes');
    $application = ($this->pendingApplication)($applicant);

    $this->actingAs($admin)
        ->get(route('admin.professional-applications.file', [$application, 'id-photo']))
        ->assertOk()
        ->assertStreamedContent('id-bytes');

    $this->actingAs($admin)
        ->get(route('admin.professional-applications.file', [$application, 'selfie']))
        ->assertOk()
        ->assertStreamedContent('selfie-bytes');
});

it('non admin cannot stream an application evidence file', function () {
    $applicant = User::factory()->create();
    $applicant->assignRole(Role::User->value);

    Storage::disk('s3')->put('fixtures/selfie.jpg', 'selfie-bytes');
    $application = ($this->pendingApplication)($applicant);

    $this->actingAs($applicant)
        ->get(route('admin.professional-applications.file', [$application, 'selfie']))
        ->assertForbidden();
});

it('admin approving an application grants the profession role without removing the base user role', function () {
    Event::fake([ProfessionalApplicationStatusChanged::class]);
    Notification::fake();

    $admin = ($this->admin)();
    $applicant = User::factory()->create();
    $applicant->assignRole(Role::User->value);

    Storage::disk('s3')->put('fixtures/selfie.jpg', 'fake-image-bytes');
    $application = ($this->pendingApplication)($applicant);

    $this->actingAs($admin)
        ->patch(route('admin.professional-applications.approve', $application))
        ->assertRedirect(route('admin.professional-applications.index'));

    $applicant->refresh();
    $application->refresh();

    expect($applicant->hasRole('physician'))->toBeTrue()
        ->and($applicant->hasRole(Role::User->value))->toBeTrue()
        ->and($applicant->hasPermissionTo(PermissionEnum::VerifiedProfessional->value))->toBeTrue()
        ->and($application->status->value)->toBe('approved')
        ->and($application->role_granted)->toBe('physician')
        ->and($applicant->profile_photo_path)->not->toBeNull();

    Storage::disk('public')->assertExists($applicant->profile_photo_path);

    Event::assertDispatched(
        ProfessionalApplicationStatusChanged::class,
        fn ($event) => $event->applicationId === $application->id
    );

    Notification::assertSentTo($applicant, ProfessionalApplicationApprovedNotification::class);
});

it('admin denying an application soft deletes it and records the reason', function () {
    Event::fake([ProfessionalApplicationStatusChanged::class]);
    Notification::fake();

    $admin = ($this->admin)();
    $applicant = User::factory()->create();
    $application = ($this->pendingApplication)($applicant);

    $this->actingAs($admin)
        ->patch(route('admin.professional-applications.reject', $application), [
            'rejection_reason' => 'Blurry ID photo.',
        ])
        ->assertRedirect(route('admin.professional-applications.index'));

    $this->assertSoftDeleted('professional_applications', ['id' => $application->id]);

    $fresh = ProfessionalApplication::withTrashed()->findOrFail($application->id);
    expect($fresh->status->value)->toBe('denied')
        ->and($fresh->rejection_reason)->toBe('Blurry ID photo.');

    $this->actingAs($admin)
        ->get(route('admin.professional-applications.index', ['status' => 'denied']))
        ->assertOk();

    Event::assertDispatched(
        ProfessionalApplicationStatusChanged::class,
        fn ($event) => $event->applicationId === $application->id
    );

    Notification::assertSentTo(
        $applicant,
        ProfessionalApplicationDeniedNotification::class
    );
});

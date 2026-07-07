<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Storage::fake('s3');
    Storage::fake('public');

    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::Admin->value);

    $applicant = User::factory()->create();
    $applicant->assignRole(Role::User->value);

    Storage::disk('s3')->put('demo/id.jpg', 'fake-id-bytes');
    Storage::disk('s3')->put('demo/selfie.jpg', 'fake-selfie-bytes');
    Storage::disk('s3')->put('demo/coe.pdf', 'fake-coe-bytes');

    $this->application = $applicant->professionalApplications()->create([
        'id_type' => 'ph_prc',
        'issuing_country' => 'PH',
        'profession' => 'Physician',
        'specialty' => 'Orthopedic',
        'license_number' => '123456',
        'id_photo_path' => 'demo/id.jpg',
        'selfie_path' => 'demo/selfie.jpg',
        'selfie_frame_paths' => ['demo/selfie.jpg'],
        'coe_path' => 'demo/coe.pdf',
        'coe_original_filename' => 'coe.pdf',
        'face_match_score' => 0.87,
        'face_match_passed' => true,
        'liveness_score' => 1.0,
        'liveness_passed' => true,
        'status' => 'pending_review',
    ]);
});

it('admin can find and approve a pending application via the sidebar link', function () {
    $this->actingAs($this->admin);

    visit(route('admin.dashboard'))
        ->assertSee('Professional Applications')
        ->screenshot(filename: 'admin-dashboard-nav')
        ->click('Professional Applications')
        ->assertPathIs('/admin/professional-applications')
        ->assertSee($this->application->user->email)
        ->assertSee('pending review')
        ->screenshot(filename: 'admin-applications-index')
        ->assertNoJavascriptErrors();

    visit(route('admin.professional-applications.show', $this->application))
        ->assertSee('Physician')
        ->assertSee('Orthopedic')
        ->assertSee('Approve')
        ->screenshot(filename: 'admin-application-show')
        ->click('Approve')
        ->assertPathIs('/admin/professional-applications')
        ->assertNoJavascriptErrors();

    $applicant = $this->application->user->fresh();
    expect($applicant->hasRole('orthopedic'))->toBeTrue()
        ->and($this->application->fresh()->status->value)->toBe('approved');
});

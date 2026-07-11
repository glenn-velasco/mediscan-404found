<?php

use App\Enums\AuditLogType;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserInvitation;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->admin = function (): User {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        return $admin;
    };
});

it('logs authentication on web login', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect();

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $user->id,
        'subject_id' => $user->id,
        'action' => 'auth.login',
        'type' => AuditLogType::Authentication->value,
        'channel' => 'web',
    ]);
});

it('logs authentication on web logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'))->assertRedirect();

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $user->id,
        'subject_id' => $user->id,
        'action' => 'auth.logout',
        'type' => AuditLogType::Authentication->value,
        'channel' => 'web',
    ]);
});

it('logs authentication on mobile login', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'phpunit-test',
    ])->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $user->id,
        'subject_id' => $user->id,
        'action' => 'auth.login',
        'type' => AuditLogType::Authentication->value,
        'channel' => 'api',
    ]);
});

it('logs authentication on mobile logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('phpunit-test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/logout')
        ->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $user->id,
        'subject_id' => $user->id,
        'action' => 'auth.logout',
        'type' => AuditLogType::Authentication->value,
        'channel' => 'api',
    ]);
});

it('logs invitation lifecycle: create, resend, delete, accept', function () {
    Notification::fake();

    $admin = ($this->admin)();

    $this->actingAs($admin)
        ->post(route('admin.invitations.store'), [
            'email' => 'invite@example.com',
            'expires_in_days' => 3,
        ]);

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $admin->id,
        'action' => 'invitation.created',
        'type' => AuditLogType::Create->value,
    ]);

    $invitation = UserInvitation::where('email', 'invite@example.com')->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.invitations.resend', $invitation));

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $admin->id,
        'action' => 'invitation.resent',
        'type' => AuditLogType::Update->value,
    ]);

    $token = $invitation->fresh()->token;

    $this->post(route('logout'));

    $this->post(route('invitation.store', $token), [
        'username' => 'juan.delacruz',
        'first_name' => 'Juan',
        'last_name' => 'dela Cruz',
        'dob' => '1990-01-15',
        'gender' => 'male',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'invitation.accepted',
        'type' => AuditLogType::Accepted->value,
    ]);

    $secondInvitation = UserInvitation::create([
        'email' => 'delete-me@example.com',
        'token' => Str::random(64),
        'invited_by' => $admin->id,
        'expires_at' => now()->addDays(3),
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.invitations.destroy', $secondInvitation));

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $admin->id,
        'action' => 'invitation.deleted',
        'type' => AuditLogType::Delete->value,
    ]);
});

it('logs user role assignment, activation, and deletion', function () {
    $admin = ($this->admin)();
    $target = User::factory()->create();
    $target->assignRole(Role::User->value);

    $this->actingAs($admin)
        ->patch(route('admin.users.role', $target), ['role' => Role::Admin->value]);

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $admin->id,
        'subject_id' => $target->id,
        'action' => 'user.role_assigned',
        'type' => AuditLogType::Update->value,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.users.activation', $target));

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $admin->id,
        'subject_id' => $target->id,
        'action' => 'user.deactivated',
        'type' => AuditLogType::Update->value,
    ]);

    $targetId = $target->id;

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $target));

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $admin->id,
        'action' => 'user.deleted',
        'type' => AuditLogType::Delete->value,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.deleted',
    ]);
    expect(
        AuditLog::where('action', 'user.deleted')->first()->metadata['user_id'] ?? null
    )->toBe($targetId);
});

it('logs professional application approval and rejection', function () {
    Storage::fake('s3');
    Storage::fake('public');

    $admin = ($this->admin)();
    $applicant = User::factory()->create();
    $applicant->assignRole(Role::User->value);

    Storage::disk('s3')->put('fixtures/selfie.jpg', 'fake-image-bytes');

    $approved = $applicant->professionalApplications()->create([
        'id_type' => 'ph_prc',
        'issuing_country' => 'PH',
        'profession' => 'Physician',
        'specialty' => 'Orthopedic',
        'license_number' => '123456',
        'id_photo_path' => 'fixtures/id.jpg',
        'selfie_path' => 'fixtures/selfie.jpg',
        'coe_path' => 'fixtures/coe.pdf',
        'status' => 'pending_review',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.professional-applications.approve', $approved));

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $admin->id,
        'subject_id' => $applicant->id,
        'action' => 'professional_application.approved',
        'type' => AuditLogType::Accepted->value,
    ]);

    $secondApplicant = User::factory()->create();

    $denied = $secondApplicant->professionalApplications()->create([
        'id_type' => 'ph_prc',
        'issuing_country' => 'PH',
        'profession' => 'Physician',
        'specialty' => 'Cardiology',
        'license_number' => '654321',
        'id_photo_path' => 'fixtures/id2.jpg',
        'selfie_path' => 'fixtures/selfie2.jpg',
        'coe_path' => 'fixtures/coe2.pdf',
        'status' => 'pending_review',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.professional-applications.reject', $denied), [
            'rejection_reason' => 'Blurry ID photo.',
        ]);

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $admin->id,
        'subject_id' => $secondApplicant->id,
        'action' => 'professional_application.rejected',
        'type' => AuditLogType::Denied->value,
    ]);
});

it('logs viewing a professional application file', function () {
    Storage::fake('s3');

    $admin = ($this->admin)();
    $applicant = User::factory()->create();

    Storage::disk('s3')->put('fixtures/selfie.jpg', 'selfie-bytes');

    $application = $applicant->professionalApplications()->create([
        'id_type' => 'ph_prc',
        'issuing_country' => 'PH',
        'id_photo_path' => 'fixtures/id.jpg',
        'selfie_path' => 'fixtures/selfie.jpg',
        'coe_path' => 'fixtures/coe.pdf',
        'status' => 'pending_review',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.professional-applications.file', [$application, 'selfie']))
        ->assertOk();

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $admin->id,
        'subject_id' => $applicant->id,
        'action' => 'professional_application.file_viewed',
        'type' => AuditLogType::View->value,
    ]);
});

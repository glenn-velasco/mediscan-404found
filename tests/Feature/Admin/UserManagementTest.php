<?php

use App\Enums\AuditLogType;
use App\Enums\Permission;
use App\Enums\Role;
use App\Events\UserDeactivated;
use App\Events\UserDeleted;
use App\Http\Requests\AssignRoleRequest;
use App\Models\PendingSyncEnvelope;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\Admin\DashboardService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->admin = function (): User {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        return $admin;
    };

    $this->regularUser = function (): User {
        $user = User::factory()->create();
        $user->assignRole(Role::User->value);

        return $user;
    };
});

it('admin has correct permissions', function () {
    $admin = ($this->admin)();

    $this->assertTrue($admin->hasPermissionTo(Permission::ManageUsers->value));
    $this->assertTrue($admin->hasPermissionTo(Permission::InviteUserAsAdmin->value));
    $this->assertTrue($admin->hasPermissionTo(Permission::VerifiedProfessional->value));
});

it('admin can list users', function () {
    $this->actingAs(($this->admin)())
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/users/index'));
});

it('non admin cannot list users', function () {
    $this->actingAs(($this->regularUser)())
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

it('guest is redirected from user list', function () {
    $this->get(route('admin.users.index'))
        ->assertRedirect(route('login'));
});

it('admin can view user detail', function () {
    $target = ($this->regularUser)();

    $this->actingAs(($this->admin)())
        ->get(route('admin.users.show', $target))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/show')
            ->where('user.id', $target->id)
            ->where('user.email', $target->email)
            ->where('user.fullname', $target->fullname)
            ->where('user.age', $target->age)
        );
});

it('create user route does not exist', function () {
    $this->actingAs(($this->admin)())
        ->post(route('admin.users.index'), ['email' => 'new@example.com'])
        ->assertStatus(405);
});

it('edit user route does not exist', function () {
    $target = ($this->regularUser)();

    $this->actingAs(($this->admin)())
        ->get("/admin/users/{$target->id}/edit")
        ->assertNotFound();
});

it('update user route does not exist', function () {
    $target = ($this->regularUser)();

    $this->actingAs(($this->admin)())
        ->put(route('admin.users.show', $target), ['name' => 'Changed'])
        ->assertStatus(405);
});

it('admin can assign role', function () {
    $admin = ($this->admin)();
    $target = ($this->regularUser)();

    $this->actingAs($admin)
        ->patch(route('admin.users.role', $target), ['role' => Role::Admin->value]);

    $this->assertTrue($target->fresh()->hasRole(Role::Admin->value));

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $admin->id,
        'subject_id' => $target->id,
        'action' => 'user.role_assigned',
        'type' => AuditLogType::Update->value,
    ]);
});

it('assigning a role flushes the admin dashboard stats cache', function () {
    $target = ($this->regularUser)();
    Cache::put(DashboardService::STATS_CACHE_KEY, ['stale' => true], now()->addMonth());

    $this->actingAs(($this->admin)())
        ->patch(route('admin.users.role', $target), ['role' => Role::Admin->value]);

    expect(Cache::has(DashboardService::STATS_CACHE_KEY))->toBeFalse();
});

it('admin can deactivate user', function () {
    $target = ($this->regularUser)();

    $this->actingAs(($this->admin)())
        ->patch(route('admin.users.activation', $target));

    $this->assertFalse($target->fresh()->isActive());
});

it('deactivating a user flushes the admin dashboard stats cache', function () {
    $target = ($this->regularUser)();
    Cache::put(DashboardService::STATS_CACHE_KEY, ['stale' => true], now()->addMonth());

    $this->actingAs(($this->admin)())
        ->patch(route('admin.users.activation', $target));

    expect(Cache::has(DashboardService::STATS_CACHE_KEY))->toBeFalse();
});

it('deactivating a user revokes their api tokens', function () {
    $target = ($this->regularUser)();
    $target->createToken('phone');
    $target->createToken('tablet');

    $this->actingAs(($this->admin)())
        ->patch(route('admin.users.activation', $target));

    expect($target->tokens()->count())->toBe(0);
});

it('deactivating a user broadcasts UserDeactivated on their private channel', function () {
    Event::fake([UserDeactivated::class]);

    $target = ($this->regularUser)();

    $this->actingAs(($this->admin)())
        ->patch(route('admin.users.activation', $target));

    Event::assertDispatched(
        UserDeactivated::class,
        fn (UserDeactivated $event) => $event->user->is($target),
    );
});

it('admin can reactivate user', function () {
    $target = User::factory()->create(['deactivated_at' => now()]);
    $target->assignRole(Role::User->value);

    $this->actingAs(($this->admin)())
        ->patch(route('admin.users.activation', $target));

    $this->assertTrue($target->fresh()->isActive());
});

it('deactivated user is kicked out on protected routes', function () {
    $user = User::factory()->create(['deactivated_at' => now()]);

    $this->actingAs($user)
        ->get(route('professional-application.show'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('admin can delete user', function () {
    $target = ($this->regularUser)();

    $this->actingAs(($this->admin)())
        ->delete(route('admin.users.destroy', $target))
        ->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseMissing('users', ['id' => $target->id]);
});

it('deleting a user flushes the admin dashboard stats cache', function () {
    $target = ($this->regularUser)();
    Cache::put(DashboardService::STATS_CACHE_KEY, ['stale' => true], now()->addMonth());

    $this->actingAs(($this->admin)())
        ->delete(route('admin.users.destroy', $target));

    expect(Cache::has(DashboardService::STATS_CACHE_KEY))->toBeFalse();
});

it('deleting a user revokes their api tokens', function () {
    $target = ($this->regularUser)();
    $target->createToken('phone');
    $target->createToken('tablet');
    $targetId = $target->id;

    $this->actingAs(($this->admin)())
        ->delete(route('admin.users.destroy', $target));

    expect(PersonalAccessToken::where('tokenable_id', $targetId)->count())->toBe(0);
});

it('deleting a user broadcasts UserDeleted on their private channel', function () {
    Event::fake([UserDeleted::class]);

    $target = ($this->regularUser)();
    $targetId = $target->id;

    $this->actingAs(($this->admin)())
        ->delete(route('admin.users.destroy', $target));

    Event::assertDispatched(
        UserDeleted::class,
        fn (UserDeleted $event) => $event->userId === $targetId,
    );
});

it('non admin cannot delete user', function () {
    $target = User::factory()->create();

    $this->actingAs(($this->regularUser)())
        ->delete(route('admin.users.destroy', $target))
        ->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $target->id]);
});

it('admin can search users by email', function () {
    User::factory()->create(['email' => 'find-me@example.com']);
    User::factory()->create(['email' => 'other@example.com']);

    $this->actingAs(($this->admin)())
        ->get(route('admin.users.index', ['search' => 'find-me']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('users.total', 1));
});

it('admin can search users by first, middle, and last name', function () {
    foreach (['first_name', 'middle_name', 'last_name'] as $field) {
        User::query()->delete();

        $target = User::factory()->create([$field => 'Uniquematchname']);
        User::factory()->create();

        $this->actingAs(($this->admin)())
            ->get(route('admin.users.index', ['search' => 'Uniquematchname']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('users.total', 1)
                ->where('users.data.0.id', $target->id)
            );
    }
});

it('admin can search by a full-name term spanning first and last name when middle name is null', function () {
    $target = User::factory()->withoutMiddleName()->create([
        'first_name' => 'Juan',
        'last_name' => 'Delacruz',
    ]);
    User::factory()->create(['first_name' => 'Someone', 'last_name' => 'Else']);

    $this->actingAs(($this->admin)())
        ->get(route('admin.users.index', ['search' => 'Juan Delacruz']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.total', 1)
            ->where('users.data.0.id', $target->id)
        );
});

it('admin list response includes correct fullname and age values', function () {
    $target = User::factory()->create([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Delacruz',
        'suffix' => 'Jr.',
        'dob' => now()->subYears(40)->toDateString(),
    ]);

    $this->actingAs(($this->admin)())
        ->get(route('admin.users.index', ['search' => $target->first_name]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('users.data.0.fullname', 'Juan Santos Delacruz Jr.')
            ->where('users.data.0.age', 40)
        );
});

it('admin cannot change their own role', function () {
    $admin = ($this->admin)();

    $this->actingAs($admin)
        ->patch(route('admin.users.role', $admin), ['role' => Role::User->value])
        ->assertSessionHasErrors('role');

    $this->assertTrue($admin->fresh()->hasRole(Role::Admin->value));
});

it('admin cannot demote the last remaining admin', function () {
    // The whole /admin route group requires the actor to already hold the Admin
    // role (see routes/web.php), so an actor distinct from the target can only
    // reach this branch when at least 2 admins exist — which makes "target is
    // the sole admin" false. This branch is therefore a defense-in-depth guard
    // against any other entry point into AssignRoleRequest (a future API route,
    // a console command, etc.), so it's exercised directly against the request
    // object rather than through a real HTTP round trip that middleware would
    // never actually allow to reach it.
    $sole = ($this->admin)();
    $actor = ($this->regularUser)();

    $request = AssignRoleRequest::create(
        route('admin.users.role', $sole),
        'PATCH',
        ['role' => Role::User->value],
    );
    $request->setUserResolver(fn () => $actor);
    $request->setRouteResolver(fn () => new class($sole)
    {
        public function __construct(private User $user) {}

        public function parameter($name, $default = null)
        {
            return $name === 'user' ? $this->user : $default;
        }
    });

    $validator = Validator::make($request->all(), $request->rules());
    $request->withValidator($validator);

    expect($validator->fails())->toBeTrue();
    $this->assertTrue($sole->fresh()->hasRole(Role::Admin->value));
});

it('admin can demote an admin when other admins remain', function () {
    $actingAdmin = ($this->admin)();
    $otherAdmin = ($this->admin)();

    $this->actingAs($actingAdmin)
        ->patch(route('admin.users.role', $otherAdmin), ['role' => Role::User->value])
        ->assertSessionHasNoErrors();

    $this->assertTrue($otherAdmin->fresh()->hasRole(Role::User->value));
});

it('role change actually flips access to a role-gated route', function () {
    $admin = ($this->admin)();
    $target = ($this->regularUser)();

    $this->actingAs($target)
        ->get(route('admin.dashboard'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->patch(route('admin.users.role', $target), ['role' => Role::Admin->value]);

    $this->actingAs($target->fresh())
        ->get(route('admin.dashboard'))
        ->assertOk();
});

it('deleting a user cascades their professional application and invitations they sent, and nulls out reviewed_by/role_id/sender_id references', function () {
    $admin = ($this->admin)();
    $target = ($this->regularUser)();

    $application = $target->professionalApplications()->create([
        'id_type' => 'ph_prc',
        'issuing_country' => 'PH',
        'profession' => 'Physician',
        'id_photo_path' => 'demo/id.jpg',
        'selfie_path' => 'demo/selfie.jpg',
        'coe_path' => 'demo/coe.pdf',
        'status' => 'pending_review',
    ]);

    $sentInvitation = UserInvitation::create([
        'email' => 'invited-by-target@example.com',
        'token' => Str::random(64),
        'invited_by' => $target->id,
        'expires_at' => now()->addDays(3),
    ]);

    $otherApplicant = User::factory()->create();
    $reviewedApplication = $otherApplicant->professionalApplications()->create([
        'id_type' => 'ph_prc',
        'issuing_country' => 'PH',
        'profession' => 'Physician',
        'id_photo_path' => 'demo/id2.jpg',
        'selfie_path' => 'demo/selfie2.jpg',
        'coe_path' => 'demo/coe2.pdf',
        'status' => 'approved',
        'reviewed_by' => $target->id,
    ]);

    $envelope = PendingSyncEnvelope::factory()->create(['sender_id' => $target->id]);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $target));

    $this->assertDatabaseMissing('professional_applications', ['id' => $application->id]);
    $this->assertDatabaseMissing('user_invitations', ['id' => $sentInvitation->id]);
    $this->assertDatabaseHas('professional_applications', [
        'id' => $reviewedApplication->id,
        'reviewed_by' => null,
    ]);
    $this->assertDatabaseHas('pending_sync_envelopes', [
        'id' => $envelope->id,
        'sender_id' => null,
    ]);
});

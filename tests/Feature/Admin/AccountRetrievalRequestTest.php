<?php

use App\Enums\Role;
use App\Events\AccountRetrievalRequestStatusChanged;
use App\Models\AccountRetrievalRequest;
use App\Models\MedicalInformation;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Storage::fake('s3');

    $this->admin = function (): User {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Admin->value);

        return $admin;
    };
});

it('non admin cannot view the account retrieval requests list', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::User->value);

    $this->actingAs($user)
        ->get(route('admin.account-retrieval-requests.index'))
        ->assertForbidden();
});

it('lists account retrieval requests paginated', function () {
    $admin = ($this->admin)();
    AccountRetrievalRequest::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('admin.account-retrieval-requests.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/account-retrieval-requests/index')
            ->has('requests.data', 3)
            ->has('requests.current_page')
        );
});

it('admin approving a request with a requester repoints that user and deletes their interim record', function () {
    Event::fake([AccountRetrievalRequestStatusChanged::class]);

    $admin = ($this->admin)();
    $oldRecord = MedicalInformation::factory()->create();
    $oldUser = User::factory()->create(['medical_information_id' => $oldRecord->id, 'email' => 'old@example.com']);
    $oldRecord->forceFill(['primary_user_id' => $oldUser->id])->save();

    $interim = MedicalInformation::factory()->create();
    $requester = User::factory()->create(['medical_information_id' => $interim->id]);

    $retrievalRequest = AccountRetrievalRequest::factory()->fromExistingAccount()->create([
        'requester_user_id' => $requester->id,
        'old_email' => 'old@example.com',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.account-retrieval-requests.approve', $retrievalRequest))
        ->assertRedirect();

    expect($requester->fresh()->medical_information_id)->toBe($oldRecord->id)
        ->and(MedicalInformation::find($interim->id))->toBeNull()
        ->and($retrievalRequest->fresh()->status->value)->toBe('approved');

    Event::assertDispatched(AccountRetrievalRequestStatusChanged::class);
});

it('admin approving a pre-registration request sends the old-email recovery link instead of repointing', function () {
    Notification::fake();

    $admin = ($this->admin)();
    $oldRecord = MedicalInformation::factory()->create();
    $oldUser = User::factory()->create(['medical_information_id' => $oldRecord->id, 'email' => 'lost@example.com']);
    $oldRecord->forceFill(['primary_user_id' => $oldUser->id])->save();

    $retrievalRequest = AccountRetrievalRequest::factory()->create([
        'requester_user_id' => null,
        'old_email' => 'lost@example.com',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.account-retrieval-requests.approve', $retrievalRequest))
        ->assertRedirect();

    expect($retrievalRequest->fresh()->status->value)->toBe('approved');

    Notification::assertSentTo($oldUser, \Illuminate\Auth\Notifications\ResetPassword::class);
});

it('admin denying leaves the request state unchanged except status and reason', function () {
    $admin = ($this->admin)();
    $requester = User::factory()->create();
    $retrievalRequest = AccountRetrievalRequest::factory()->fromExistingAccount()->create([
        'requester_user_id' => $requester->id,
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.account-retrieval-requests.deny', $retrievalRequest), [
            'rejection_reason' => 'Name mismatch.',
        ])
        ->assertRedirect();

    $fresh = $retrievalRequest->fresh();

    expect($fresh->status->value)->toBe('denied')
        ->and($fresh->rejection_reason)->toBe('Name mismatch.')
        ->and($requester->fresh()->medical_information_id)->toBe($requester->medical_information_id);
});

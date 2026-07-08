<?php

use App\Enums\Gender;
use App\Enums\Role;
use App\Events\TransfusionConsentUpdated;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->userWithMedicalInfo = function (array $overrides = []): User {
        $user = User::factory()->create();
        $user->assignRole(Role::User->value);

        $user->medicalInformation()->create(array_merge([
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'date_of_birth' => '1992-04-10',
            'gender' => Gender::Female,
            'no_blood_transfusion' => false,
        ], $overrides));

        return $user;
    };
});

it('redirects guests to login', function () {
    $this->patch(route('transfusion-consent.update'), ['no_blood_transfusion' => false])
        ->assertRedirect(route('login'));
});

it('lets a regular user without any professional permission record consent', function () {
    $user = ($this->userWithMedicalInfo)(['no_blood_transfusion' => true]);

    $this->actingAs($user)
        ->patch(route('transfusion-consent.update'), ['no_blood_transfusion' => false])
        ->assertRedirect();

    $medicalInfo = $user->medicalInformation->fresh();
    expect($medicalInfo->no_blood_transfusion)->toBeFalse()
        ->and($medicalInfo->transfusion_decision_by)->toBe($user->id)
        ->and($medicalInfo->transfusion_decision_at)->not->toBeNull();
});

it('lets a user record a refusal', function () {
    $user = ($this->userWithMedicalInfo)();

    $this->actingAs($user)
        ->patch(route('transfusion-consent.update'), ['no_blood_transfusion' => true])
        ->assertRedirect();

    expect($user->medicalInformation->fresh()->no_blood_transfusion)->toBeTrue();
});

it('resets the professional witnesses when the decision changes', function () {
    $witness = User::factory()->create();
    $user = ($this->userWithMedicalInfo)(['no_blood_transfusion' => true]);
    $user->medicalInformation->forceFill([
        'transfusion_decision_by' => $user->id,
        'transfusion_decision_at' => now()->subDay(),
        'transfusion_witnesses' => [
            ['user_id' => $witness->id, 'name' => $witness->name, 'witnessed_at' => now()->subDay()->toIso8601String()],
        ],
    ])->save();

    $this->actingAs($user)
        ->patch(route('transfusion-consent.update'), ['no_blood_transfusion' => false]);

    expect($user->medicalInformation->fresh()->transfusion_witnesses)->toBe([]);
});

it('broadcasts a transfusion consent update', function () {
    Event::fake([TransfusionConsentUpdated::class]);

    $user = ($this->userWithMedicalInfo)();

    $this->actingAs($user)
        ->patch(route('transfusion-consent.update'), ['no_blood_transfusion' => true]);

    Event::assertDispatched(TransfusionConsentUpdated::class, fn (TransfusionConsentUpdated $event) => $event->userId === $user->id
        && $event->noBloodTransfusion === true
        && $event->witnessCount === 0);
});

it('validates the consent payload', function () {
    $user = ($this->userWithMedicalInfo)();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->patch(route('transfusion-consent.update'), [])
        ->assertSessionHasErrors('no_blood_transfusion');
});

it('returns 404 when the user has no medical information', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('transfusion-consent.update'), ['no_blood_transfusion' => false])
        ->assertNotFound();
});

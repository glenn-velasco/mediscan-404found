<?php

use App\Events\ProfessionalApplicationStatusChanged;
use App\Jobs\ProcessProfessionalApplication;
use App\Models\ProfessionalApplication;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('s3');

    $this->payload = fn (array $overrides = []): array => array_merge([
        'id_type' => 'ph_prc',
        'id_photo' => UploadedFile::fake()->image('id.jpg'),
        'selfie_frames' => [
            UploadedFile::fake()->image('selfie-0.jpg'),
            UploadedFile::fake()->image('selfie-1.jpg'),
            UploadedFile::fake()->image('selfie-2.jpg'),
        ],
        'flash_frames' => [
            UploadedFile::fake()->image('flash-red.jpg'),
            UploadedFile::fake()->image('flash-green.jpg'),
            UploadedFile::fake()->image('flash-blue.jpg'),
        ],
        'flash_colors' => ['red', 'green', 'blue'],
    ], $overrides);
});

it('submits a professional application and dispatches the verification job', function () {
    Queue::fake();
    Event::fake([ProfessionalApplicationStatusChanged::class]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('professional-application.store'), ($this->payload)())
        ->assertRedirect(route('professional-application.show'));

    $application = ProfessionalApplication::where('user_id', $user->id)->first();

    expect($application)->not->toBeNull()
        ->and($application->status->value)->toBe('processing')
        ->and($application->id_type->value)->toBe('ph_prc')
        ->and($application->issuing_country)->toBe('PH');

    Storage::disk('s3')->assertExists($application->id_photo_path);
    Storage::disk('s3')->assertExists($application->selfie_path);

    expect($application->liveness_flash_frames)->toHaveCount(3);
    foreach ($application->liveness_flash_frames as $flashFrame) {
        Storage::disk('s3')->assertExists($flashFrame['path']);
    }

    Queue::assertPushed(ProcessProfessionalApplication::class, fn ($job) => $job->applicationId === $application->id);

    Event::assertDispatched(
        ProfessionalApplicationStatusChanged::class,
        fn ($event) => $event->applicationId === $application->id
    );
});

it('rejects submission with a missing required file', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('professional-application.store'), ($this->payload)(['id_photo' => null]))
        ->assertSessionHasErrors('id_photo');
});

it('rejects submission with too few liveness capture frames', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('professional-application.store'), ($this->payload)([
            'selfie_frames' => [UploadedFile::fake()->image('only-one.jpg')],
        ]))
        ->assertSessionHasErrors('selfie_frames');
});

it('rejects submission when flash_colors count does not match flash_frames', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('professional-application.store'), ($this->payload)([
            'flash_colors' => ['red', 'green'],
        ]))
        ->assertSessionHasErrors('flash_colors');
});

it('rejects submission with an invalid flash color', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('professional-application.store'), ($this->payload)([
            'flash_colors' => ['red', 'green', 'purple'],
        ]))
        ->assertSessionHasErrors('flash_colors.2');
});

it('blocks a second submission while one is still active', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)->post(route('professional-application.store'), ($this->payload)());

    $this->actingAs($user)
        ->post(route('professional-application.store'), ($this->payload)())
        ->assertSessionHasErrors();
});

it('a user cannot see another users application via the status page transform', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($owner)->post(route('professional-application.store'), ($this->payload)());

    $this->actingAs($other)
        ->get(route('professional-application.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('application', null));
});

it('the owner sees their application status reflected correctly on the status page', function (string $status) {
    $owner = User::factory()->create();

    $application = $owner->professionalApplications()->create([
        'id_type' => 'ph_prc',
        'issuing_country' => 'PH',
        'id_photo_path' => 'demo/id.jpg',
        'selfie_path' => 'demo/selfie.jpg',
        'status' => $status,
        'rejection_reason' => $status === 'denied' ? 'Blurry ID photo.' : null,
        'deleted_at' => $status === 'denied' ? now() : null,
    ]);

    $this->actingAs($owner)
        ->get(route('professional-application.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('professional-application/show')
            ->where('application.id', $application->id)
            ->where('application.status', $status)
        );
})->with([
    'processing',
    'pending_review',
    'approved',
    'denied',
    'auto_rejected',
]);

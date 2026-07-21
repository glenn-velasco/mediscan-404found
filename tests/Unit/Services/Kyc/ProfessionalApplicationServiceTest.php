<?php

use App\Enums\WorkflowStatus;
use App\Exceptions\ProfessionalApplicationAlreadyPendingException;
use App\Models\ProfessionalApplication;
use App\Models\User;
use App\Repositories\Eloquent\ProfessionalApplicationRepository;
use App\Services\Kyc\ProfessionalApplicationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('converts a unique-constraint race into the friendly already-pending exception', function () {
    Storage::fake('s3');
    Queue::fake();

    $user = User::factory()->create();

    // Simulate the race window: another request's insert has already landed
    // while this request's own activeFor() check missed it (simulated below
    // via a subclass overriding just that one method), so the only thing
    // left to catch the double-submission is the DB's partial unique index.
    $user->professionalApplications()->create([
        'id_type' => 'ph_prc',
        'issuing_country' => 'PH',
        'id_photo_path' => 'id.jpg',
        'selfie_path' => 'selfie.jpg',
        'status' => WorkflowStatus::Processing->value,
    ]);

    $this->app->bind(ProfessionalApplicationRepository::class, fn () => new class(new ProfessionalApplication) extends ProfessionalApplicationRepository
    {
        public function activeFor(int $userId): ?ProfessionalApplication
        {
            return null;
        }
    });

    $service = app(ProfessionalApplicationService::class);

    $payload = [
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
    ];

    expect(fn () => $service->submit($user, $payload))
        ->toThrow(ProfessionalApplicationAlreadyPendingException::class);
});

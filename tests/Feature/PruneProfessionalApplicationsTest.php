<?php

use App\Models\ProfessionalApplication;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('s3');

    $this->makeApplication = function (string $status, CarbonInterface $updatedAt, bool $trashed = false): ProfessionalApplication {
        $user = User::factory()->create();
        $folder = "professional-applications/{$user->id}/".fake()->uuid();

        Storage::disk('s3')->put("{$folder}/id.jpg", 'id');
        Storage::disk('s3')->put("{$folder}/selfie-frame-0.jpg", 'selfie');
        Storage::disk('s3')->put("{$folder}/coe.pdf", 'coe');

        $application = $user->professionalApplications()->create([
            'id_type' => 'ph_prc',
            'issuing_country' => 'PH',
            'id_photo_path' => "{$folder}/id.jpg",
            'selfie_path' => "{$folder}/selfie-frame-0.jpg",
            'coe_path' => "{$folder}/coe.pdf",
            'status' => $status,
        ]);

        $application->timestamps = false;
        $application->forceFill([
            'updated_at' => $updatedAt,
            'deleted_at' => $trashed ? $updatedAt : null,
        ])->save();

        return $application;
    };
});

it('prunes stale auto-rejected, denied, and pending-review applications with their files', function () {
    $autoRejected = ($this->makeApplication)('auto_rejected', now()->subDays(6), trashed: true);
    $denied = ($this->makeApplication)('denied', now()->subDays(6), trashed: true);
    $pending = ($this->makeApplication)('pending_review', now()->subDays(6));

    $this->artisan('professional-applications:prune')
        ->expectsOutputToContain('3 professional application(s) pruned.')
        ->assertSuccessful();

    foreach ([$autoRejected, $denied, $pending] as $application) {
        $this->assertDatabaseMissing('professional_applications', ['id' => $application->id]);

        expect(Storage::disk('s3')->exists($application->id_photo_path))->toBeFalse()
            ->and(Storage::disk('s3')->exists($application->coe_path))->toBeFalse();
    }
});

it('keeps prunable-status applications newer than the cutoff', function () {
    $recent = ($this->makeApplication)('denied', now()->subDays(4), trashed: true);

    $this->artisan('professional-applications:prune')
        ->expectsOutputToContain('0 professional application(s) pruned.');

    $this->assertDatabaseHas('professional_applications', ['id' => $recent->id]);
    expect(Storage::disk('s3')->exists($recent->id_photo_path))->toBeTrue();
});

it('never prunes processing or approved applications regardless of age', function () {
    $processing = ($this->makeApplication)('processing', now()->subDays(30));
    $approved = ($this->makeApplication)('approved', now()->subDays(30));

    $this->artisan('professional-applications:prune')
        ->expectsOutputToContain('0 professional application(s) pruned.');

    $this->assertDatabaseHas('professional_applications', ['id' => $processing->id]);
    $this->assertDatabaseHas('professional_applications', ['id' => $approved->id]);
});

it('honours a custom day threshold', function () {
    $application = ($this->makeApplication)('denied', now()->subDays(3), trashed: true);

    $this->artisan('professional-applications:prune', ['--days' => 2])
        ->expectsOutputToContain('1 professional application(s) pruned.');

    $this->assertDatabaseMissing('professional_applications', ['id' => $application->id]);
});

it('is scheduled to run daily', function () {
    $events = collect(app(Schedule::class)->events());

    expect($events->contains(fn ($event) => str_contains($event->command ?? '', 'professional-applications:prune')
        && $event->expression === '0 0 * * *'))->toBeTrue();
});

<?php

use App\Contracts\Kyc\FaceMatchClientContract;
use App\Contracts\Kyc\OcrClientContract;
use App\Enums\ProfessionalApplicationStatus;
use App\Jobs\ProcessProfessionalApplication;
use App\Models\ProfessionalApplication;
use App\Models\User;
use App\Services\Kyc\IdVerifierResolver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function passingLivenessResponse(): array
{
    return ['live' => true, 'score' => 1.0, 'blink_detected' => true, 'color_reflection_passed' => true, 'frames_analyzed' => 3];
}

beforeEach(function () {
    Storage::fake('s3');
    Event::fake();

    $this->application = function (): ProfessionalApplication {
        $user = User::factory()->create();

        Storage::disk('s3')->put('id.jpg', 'fake-id-bytes');
        Storage::disk('s3')->put('selfie-0.jpg', 'fake-selfie-bytes-0');
        Storage::disk('s3')->put('selfie-1.jpg', 'fake-selfie-bytes-1');
        Storage::disk('s3')->put('selfie-2.jpg', 'fake-selfie-bytes-2');

        return $user->professionalApplications()->create([
            'id_type' => 'ph_prc',
            'issuing_country' => 'PH',
            'id_photo_path' => 'id.jpg',
            'selfie_path' => 'selfie-2.jpg',
            'selfie_frame_paths' => ['selfie-0.jpg', 'selfie-1.jpg', 'selfie-2.jpg'],
            'coe_path' => 'coe.pdf',
            'status' => 'processing',
        ]);
    };

    $this->run = function (ProfessionalApplication $application) {
        (new ProcessProfessionalApplication($application->id))->handle(
            app(OcrClientContract::class),
            app(FaceMatchClientContract::class),
            app(IdVerifierResolver::class),
        );
    };
});

it('auto rejects when the id has no readable text', function () {
    Http::fake(['*/ocr' => Http::response(['text' => ''])]);

    $application = ($this->application)();
    ($this->run)($application);

    expect($application->fresh()->status)->toBe(ProfessionalApplicationStatus::AutoRejected)
        ->and($application->fresh()->rejection_reason)->toContain('unreadable');
});

it('auto rejects when profession or license number cannot be extracted', function () {
    Http::fake(['*/ocr' => Http::response(['text' => 'Some unrelated ID text with no fields'])]);

    $application = ($this->application)();
    ($this->run)($application);

    expect($application->fresh()->status)->toBe(ProfessionalApplicationStatus::AutoRejected)
        ->and($application->fresh()->rejection_reason)->toContain('incomplete');
});

it('does not auto reject a banner-only profession as long as a known specialty keyword matches', function () {
    Http::fake([
        '*/ocr' => Http::response(['text' => "NURSING\nLicense No. 123456"]),
        '*/liveness' => Http::response(passingLivenessResponse()),
        '*/compare' => Http::response(['match' => true, 'score' => 0.92, 'faces_detected' => ['source' => 1, 'target' => 1]]),
    ]);

    $application = ($this->application)();
    ($this->run)($application);

    $fresh = $application->fresh();

    expect($fresh->status)->toBe(ProfessionalApplicationStatus::PendingReview)
        ->and($fresh->profession)->toBeNull()
        ->and($fresh->specialty)->toBe('Nursing');
});

it('degrades to pending review when the ocr service is unavailable', function () {
    Http::fake(['*/ocr' => Http::response('', 500)]);

    $application = ($this->application)();
    ($this->run)($application);

    $fresh = $application->fresh();

    expect($fresh->status)->toBe(ProfessionalApplicationStatus::PendingReview)
        ->and($fresh->verification_notes)->toContain('OCR service unavailable')
        ->and($fresh->ocr_extracted_data)->toBeNull();
});

it('auto rejects when no blink is detected during the liveness capture', function () {
    Http::fake([
        '*/ocr' => Http::response(['text' => "Profession: Physician\nLicense No. 123456"]),
        '*/liveness' => Http::response(['live' => false, 'score' => 0.3, 'blink_detected' => false, 'color_reflection_passed' => true]),
    ]);

    $application = ($this->application)();
    ($this->run)($application);

    $fresh = $application->fresh();

    expect($fresh->status)->toBe(ProfessionalApplicationStatus::AutoRejected)
        ->and($fresh->liveness_passed)->toBeFalse()
        ->and($fresh->rejection_reason)->toBe('Liveness check failed — no blink detected.');
});

it('auto rejects when the color flash does not reflect on the face', function () {
    Http::fake([
        '*/ocr' => Http::response(['text' => "Profession: Physician\nLicense No. 123456"]),
        '*/liveness' => Http::response(['live' => false, 'score' => 0.5, 'blink_detected' => true, 'color_reflection_passed' => false]),
    ]);

    $application = ($this->application)();
    ($this->run)($application);

    $fresh = $application->fresh();

    expect($fresh->status)->toBe(ProfessionalApplicationStatus::AutoRejected)
        ->and($fresh->liveness_passed)->toBeFalse()
        ->and($fresh->rejection_reason)->toBe('Liveness check failed — the on-screen color flash did not reflect on the face as expected.');
});

it('degrades to pending review when the liveness service is unavailable', function () {
    Http::fake([
        '*/ocr' => Http::response(['text' => "Profession: Physician\nLicense No. 123456"]),
        '*/liveness' => Http::response('', 500),
    ]);

    $application = ($this->application)();
    ($this->run)($application);

    $fresh = $application->fresh();

    expect($fresh->status)->toBe(ProfessionalApplicationStatus::PendingReview)
        ->and($fresh->verification_notes)->toContain('Liveness check service unavailable')
        ->and($fresh->liveness_score)->toBeNull();
});

it('auto rejects when no face is detected during face match', function () {
    Http::fake([
        '*/ocr' => Http::response(['text' => "Profession: Physician\nLicense No. 123456"]),
        '*/liveness' => Http::response(passingLivenessResponse()),
        '*/compare' => Http::response(['match' => false, 'score' => 0.0, 'faces_detected' => ['source' => 0, 'target' => 1]], 422),
    ]);

    $application = ($this->application)();
    ($this->run)($application);

    expect($application->fresh()->status)->toBe(ProfessionalApplicationStatus::AutoRejected)
        ->and($application->fresh()->rejection_reason)->toContain('No face detected');
});

it('auto rejects when the face match score is below the hard floor', function () {
    Http::fake([
        '*/ocr' => Http::response(['text' => "Profession: Physician\nLicense No. 123456"]),
        '*/liveness' => Http::response(passingLivenessResponse()),
        '*/compare' => Http::response(['match' => false, 'score' => 0.1, 'faces_detected' => ['source' => 1, 'target' => 1]]),
    ]);

    $application = ($this->application)();
    ($this->run)($application);

    expect($application->fresh()->status)->toBe(ProfessionalApplicationStatus::AutoRejected)
        ->and($application->fresh()->face_match_score)->toBe(0.1)
        ->and($application->fresh()->rejection_reason)->toContain('below minimum threshold');
});

it('moves to pending review without auto approving when liveness and face match both pass', function () {
    Http::fake([
        '*/ocr' => Http::response(['text' => "Profession: Physician\nLicense No. 123456"]),
        '*/liveness' => Http::response(passingLivenessResponse()),
        '*/compare' => Http::response(['match' => true, 'score' => 0.92, 'faces_detected' => ['source' => 1, 'target' => 1]]),
    ]);

    $application = ($this->application)();
    ($this->run)($application);

    $fresh = $application->fresh();

    expect($fresh->status)->toBe(ProfessionalApplicationStatus::PendingReview)
        ->and($fresh->liveness_passed)->toBeTrue()
        ->and($fresh->face_match_passed)->toBeTrue()
        ->and($fresh->profession)->toBe('Physician')
        ->and($fresh->license_number)->toBe('123456');
});

it('degrades to pending review when the face match service is unavailable', function () {
    Http::fake([
        '*/ocr' => Http::response(['text' => "Profession: Physician\nLicense No. 123456"]),
        '*/liveness' => Http::response(passingLivenessResponse()),
        '*/compare' => Http::response('', 500),
    ]);

    $application = ($this->application)();
    ($this->run)($application);

    $fresh = $application->fresh();

    expect($fresh->status)->toBe(ProfessionalApplicationStatus::PendingReview)
        ->and($fresh->verification_notes)->toContain('unavailable')
        ->and($fresh->face_match_score)->toBeNull();
});

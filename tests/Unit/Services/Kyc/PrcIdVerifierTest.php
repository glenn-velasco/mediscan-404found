<?php

use App\Enums\IdType;
use App\Services\Kyc\IdVerifiers\PrcIdVerifier;

beforeEach(function () {
    $this->verifier = new PrcIdVerifier;
});

it('reports its id type', function () {
    expect($this->verifier->idType())->toBe(IdType::PhPrc);
});

it('extracts profession, specialty, and license number from well formed ocr text', function () {
    $text = "Republic of the Philippines\nProfessional Regulation Commission\nName: Juan Dela Cruz\nProfession: Physician - Orthopedic\nLicense No. 123456\nValid Until 12/31/2027";

    $fields = $this->verifier->extractFields($text);

    expect($fields['profession'])->toContain('Physician')
        ->and($fields['specialty'])->toBe('Orthopedic')
        ->and($fields['license_number'])->toBe('123456')
        ->and($fields['full_name'])->toBe('Juan Dela Cruz')
        ->and($fields['license_expiry'])->toBe('12/31/2027');
});

it('returns nulls for fields it cannot confidently extract', function () {
    $fields = $this->verifier->extractFields('Some unrelated text with no recognizable fields');

    expect($fields['profession'])->toBeNull()
        ->and($fields['specialty'])->toBeNull()
        ->and($fields['license_number'])->toBeNull();
});

it('accepts "Registration No." as a synonym for the license number label', function () {
    $text = "Republic of the Philippines\nProfessional Regulation Commission\nName: Juan Dela Cruz\nProfession: Nursing\nRegistration No. 123456\nValid Until 12/31/2027";

    $fields = $this->verifier->extractFields($text);

    expect($fields['license_number'])->toBe('123456');
});

it('falls back to the matched specialty as the profession when there is no explicit Profession label', function () {
    $text = "Republic of the Philippines\nProfessional Regulation Commission\nName: Juan Dela Cruz\nNURSING\nRegistration No. 123456";

    $fields = $this->verifier->extractFields($text);

    expect($fields['profession'])->toBe('Nursing')
        ->and($fields['specialty'])->toBe('Nursing');
});

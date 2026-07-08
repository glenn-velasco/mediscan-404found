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

it('leaves profession null rather than duplicating specialty when there is no explicit Profession label', function () {
    $text = "Republic of the Philippines\nProfessional Regulation Commission\nName: Juan Dela Cruz\nNURSING\nRegistration No. 123456";

    $fields = $this->verifier->extractFields($text);

    expect($fields['profession'])->toBeNull()
        ->and($fields['specialty'])->toBe('Nursing');
});

it('does not let a blank Profession label swallow the next line as its value', function () {
    $text = "Republic of the Philippines\nProfessional Regulation Commission\nProfession:\nName: Juan Dela Cruz\nLicense No. 123456";

    $fields = $this->verifier->extractFields($text);

    expect($fields['profession'])->toBeNull()
        ->and($fields['full_name'])->toBe('Juan Dela Cruz');
});

it('does not truncate a license number longer than 7 digits', function () {
    $text = "Profession: Nursing\nLicense No. 12345678\nValid Until 12/31/2027";

    $fields = $this->verifier->extractFields($text);

    expect($fields['license_number'])->toBe('12345678');
});

it('does not match "Name" inside "Surname" for the full name field', function () {
    $text = "Surname: Dela Cruz\nGiven Name: Juan Miguel\nProfession: Nursing\nLicense No. 123456";

    $fields = $this->verifier->extractFields($text);

    expect($fields['full_name'])->not->toBe('Dela Cruz');
});

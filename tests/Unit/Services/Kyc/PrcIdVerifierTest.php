<?php

use App\Enums\IdType;
use App\Services\Kyc\IdVerifiers\PrcIdVerifier;

beforeEach(function () {
    $this->verifier = new PrcIdVerifier;
});

it('reports its id type', function () {
    expect($this->verifier->idType())->toBe(IdType::PhPrc);
});

it('extracts profession and license number from well formed ocr text', function () {
    $text = "Republic of the Philippines\nProfessional Regulation Commission\nName: Juan Dela Cruz\nProfession: Physician - Orthopedic\nLicense No. 123456\nValid Until 12/31/2027";

    $fields = $this->verifier->extractFields($text);

    expect($fields['profession'])->toContain('Physician')
        ->and($fields['license_number'])->toBe('123456')
        ->and($fields['full_name'])->toBe('Juan Dela Cruz')
        ->and($fields['license_expiry'])->toBe('12/31/2027');
});

it('returns nulls for fields it cannot confidently extract', function () {
    $fields = $this->verifier->extractFields('Some unrelated text with no recognizable fields');

    expect($fields['profession'])->toBeNull()
        ->and($fields['license_number'])->toBeNull();
});

it('accepts "Registration No." as a synonym for the license number label', function () {
    $text = "Republic of the Philippines\nProfessional Regulation Commission\nName: Juan Dela Cruz\nProfession: Nursing\nRegistration No. 123456\nValid Until 12/31/2027";

    $fields = $this->verifier->extractFields($text);

    expect($fields['license_number'])->toBe('123456');
});

it('extracts a standalone profession banner as the profession when there is no explicit Profession label', function () {
    $text = "Republic of the Philippines\nProfessional Regulation Commission\nName: Juan Dela Cruz\nNURSING\nRegistration No. 123456";

    $fields = $this->verifier->extractFields($text);

    expect($fields['profession'])->toBe('Nursing');
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

it('recognizes "Nurse" as a profession banner', function () {
    $text = "Republic of the Philippines\nProfessional Regulation Commission\nRegistration No. 0012345\nNURSE";

    $fields = $this->verifier->extractFields($text);

    expect($fields['profession'])->toBe('Nurse')
        ->and($fields['license_number'])->toBe('0012345');
});

it('extracts full name from separate LAST NAME / FIRST NAME / MIDDLE NAME fields', function () {
    $text = "Republic of the Philippines\nProfessional Regulation Commission\nLAST NAME\tDELA CRUZ\nFIRST NAME\tJUAN\nMIDDLE NAME\tSANTOS\nREGISTRATION NO.\t0012345\nNURSE";

    $fields = $this->verifier->extractFields($text);

    expect($fields['full_name'])->toBe('JUAN SANTOS DELA CRUZ')
        ->and($fields['profession'])->toBe('Nurse')
        ->and($fields['license_number'])->toBe('0012345');
});

it('extracts full name without middle name when MIDDLE NAME is absent', function () {
    $text = "LAST NAME\tDELA CRUZ\nFIRST NAME\tJUAN\nRegistration No. 0012345\nNURSE";

    $fields = $this->verifier->extractFields($text);

    expect($fields['full_name'])->toBe('JUAN DELA CRUZ');
});

it('handles the nurse PRC ID layout with registration number', function () {
    $text = "Republic of the Philippines\nProfessional Regulation Commission\nProfessional Identification Card\nLAST NAME\tDELA CRUZ\nFIRST NAME\tJUAN\nMIDDLE NAME\tSANTOS\nREGISTRATION NO.\t0012345\nREGISTRATION DATE\t04/05/2019\nVALID UNTIL\t11/25/2022\nNURSE";

    $fields = $this->verifier->extractFields($text);

    expect($fields['profession'])->toBe('Nurse')
        ->and($fields['full_name'])->toBe('JUAN SANTOS DELA CRUZ')
        ->and($fields['license_number'])->toBe('0012345')
        ->and($fields['license_expiry'])->toBe('11/25/2022');
});

it('returns a null profession when the card has no profession label or banner', function () {
    $text = "Republic of the Philippines\nProfessional Regulation Commission\nName: Juan Dela Cruz\nRegistration No. 0012345\nSomeUnrecognizedPromoText99";

    $fields = $this->verifier->extractFields($text);

    expect($fields['profession'])->toBeNull();
});

it('recognizes GOSURF50 as the profession on the test PRC ID card', function () {
    // Not a real profession - it's the promo text printed on a specific
    // fake PRC ID card used to test this pipeline end-to-end.
    $text = "Republic of the Philippines\nProfessional Regulation Commission\nName: Juan Dela Cruz\nRegistration No. 0012345\nGOSURF50";

    $fields = $this->verifier->extractFields($text);

    expect($fields['profession'])->toBe('GOSURF50');
});

it('extracts full name and license expiry from a column-layout card where all labels precede all values', function () {
    // Google Cloud Vision's DOCUMENT_TEXT_DETECTION reads some PRC card
    // scans column-by-column: every label first, then every value in the
    // same order, rather than each label sharing a line with its value.
    $text = "Republic of the Philippines\nPROFESSIONAL REGULATION COMMISSION\nPROFESSIONAL IDENTIFICATION CARD\nLAST NAME\nFIRST NAME\nMIDDLE NAME\nREGISTRATION NO.\nREGISTRATION DATE\nVALID UNTIL\nVelasco\nGlenn Luis\nGarin\n7392104\n07/12/2018\n► 10/01/2022\nGOSURF50";

    $fields = $this->verifier->extractFields($text);

    expect($fields['full_name'])->toBe('Glenn Luis Garin Velasco')
        ->and($fields['license_number'])->toBe('7392104')
        ->and($fields['license_expiry'])->toBe('10/01/2022')
        ->and($fields['profession'])->toBe('GOSURF50');
});

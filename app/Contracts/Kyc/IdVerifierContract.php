<?php

namespace App\Contracts\Kyc;

use App\Enums\IdType;

interface IdVerifierContract
{
    public function idType(): IdType;

    /**
     * Parse OCR output into the structured fields a professional application
     * needs. Any field that can't be confidently resolved is returned null.
     *
     * @return array{profession: ?string, specialty: ?string, license_number: ?string, license_expiry: ?string, full_name: ?string}
     */
    public function extractFields(string $fullText): array;
}

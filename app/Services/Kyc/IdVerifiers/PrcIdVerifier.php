<?php

namespace App\Services\Kyc\IdVerifiers;

use App\Contracts\Kyc\IdVerifierContract;
use App\Enums\IdType;

class PrcIdVerifier implements IdVerifierContract
{
    /**
     * Curated list of PRC professional board / specialty titles we know how
     * to recognize on the "Profession" line of the ID. Needs tuning against
     * real samples as more are seen - see docs/TODO.MD.
     *
     * @var array<int, string>
     */
    private const KNOWN_SPECIALTIES = [
        'Orthopedic', 'Orthopedics', 'Cardiology', 'Pediatrics', 'Pediatrician',
        'Neurology', 'Dermatology', 'Psychiatry', 'Radiology', 'Oncology',
        'Anesthesiology', 'Surgery', 'Internal Medicine', 'Family Medicine',
        'Obstetrics and Gynecology', 'OB-GYN', 'Urology', 'Ophthalmology',
        'Otolaryngology', 'Nursing', 'Midwifery', 'Physical Therapy',
        'Pharmacy', 'Medical Technology', 'Radiologic Technology',
    ];

    public function idType(): IdType
    {
        return IdType::PhPrc;
    }

    /**
     * @return array{profession: ?string, specialty: ?string, license_number: ?string, license_expiry: ?string, full_name: ?string}
     */
    public function extractFields(string $fullText): array
    {
        return [
            'profession' => $this->extractProfession($fullText),
            'specialty' => $this->extractSpecialty($fullText),
            'license_number' => $this->extractLicenseNumber($fullText),
            'license_expiry' => $this->extractLicenseExpiry($fullText),
            'full_name' => $this->extractFullName($fullText),
        ];
    }

    private function extractProfession(string $text): ?string
    {
        // \b after "Profession" keeps this from matching inside
        // "Professional Regulation Commission" (a header line present on
        // every PRC ID), which would otherwise swallow the real field.
        // Horizontal whitespace only (not \s, which also matches \n) between
        // the label and the value - otherwise a blank "Profession:" line
        // lets the match skip straight over the newline and capture the
        // *next* label's entire line as the profession. The quantifiers
        // between the label and `(.+)` are possessive (`?+`/`*+`) so PCRE
        // can't backtrack into giving up the colon it already consumed and
        // have `(.+)` capture just ":" when the value itself is blank.
        if (preg_match('/\bProfession\b[ \t]*+[:\-]?+[ \t]*+(.+)/i', $text, $matches)) {
            $value = trim($matches[1]);

            return $value !== '' ? $value : null;
        }

        return null;
    }

    private function extractSpecialty(string $text): ?string
    {
        foreach (self::KNOWN_SPECIALTIES as $specialty) {
            if (preg_match('/\b'.preg_quote($specialty, '/').'\b/i', $text)) {
                return $specialty;
            }
        }

        return null;
    }

    private function extractLicenseNumber(string $text): ?string
    {
        // "Registration No." is the label some PRC layouts use in place of
        // "License No." for the same field. The `(?!\d)` guard stops a
        // bounded {4,7} quantifier from silently truncating a longer run of
        // digits (e.g. an 8-digit number) to its first 7 digits - either the
        // full run is captured, or the match fails outright rather than
        // storing a corrupted number.
        if (preg_match('/(?:License|Registration)\s*No\.?\s*[:\-]?\s*([0-9]{4,})(?!\d)/i', $text, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractLicenseExpiry(string $text): ?string
    {
        if (preg_match('/(?:Valid\s*Until|Expiry|Expiration)\s*[:\-]?\s*([0-9]{1,2}[\/\-][0-9]{1,2}[\/\-][0-9]{2,4})/i', $text, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractFullName(string $text): ?string
    {
        // \b before "Name" keeps this from matching inside "Surname" (whose
        // last four letters are "name"), which would otherwise capture the
        // surname alone instead of the intended full-name field. See
        // extractProfession() for why the quantifiers are possessive.
        if (preg_match('/\bName\b[ \t]*+[:\-]?+[ \t]*+(.+)/i', $text, $matches)) {
            $value = trim($matches[1]);

            return $value !== '' ? $value : null;
        }

        return null;
    }
}

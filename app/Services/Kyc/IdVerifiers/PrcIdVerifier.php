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
        $specialty = $this->extractSpecialty($fullText);

        return [
            // Some PRC layouts print the profession/board only as an
            // unlabeled banner (e.g. "PROFESSIONAL TEACHER") rather than a
            // "Profession:" line - fall back to the matched specialty
            // keyword so those IDs aren't rejected purely for lacking the
            // literal label.
            'profession' => $this->extractProfession($fullText) ?? $specialty,
            'specialty' => $specialty,
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
        if (preg_match('/\bProfession\b\s*[:\-]?\s*(.+)/i', $text, $matches)) {
            return trim($matches[1]);
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
        // "License No." for the same field.
        if (preg_match('/(?:License|Registration)\s*No\.?\s*[:\-]?\s*([0-9]{4,7})/i', $text, $matches)) {
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
        if (preg_match('/Name\s*[:\-]?\s*(.+)/i', $text, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }
}

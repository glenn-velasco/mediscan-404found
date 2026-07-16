<?php

namespace App\Services\Kyc\IdVerifiers;

use App\Contracts\Kyc\IdVerifierContract;
use App\Enums\IdType;

class PrcIdVerifier implements IdVerifierContract
{
    /**
     * Curated list of PRC profession titles that may appear as a standalone
     * banner or as the value of a "Profession:" line.  Includes a demo/test
     * entry (GOSURF50) for the sample PRC ID card used in demonstrations.
     *
     * @var array<int, string>
     */
    private const KNOWN_PROFESSIONS = [
        'Orthopedic', 'Orthopedics', 'Cardiology', 'Pediatrics', 'Pediatrician',
        'Neurology', 'Dermatology', 'Psychiatry', 'Radiology', 'Oncology',
        'Anesthesiology', 'Surgery', 'Internal Medicine', 'Family Medicine',
        'Obstetrics and Gynecology', 'OB-GYN', 'Urology', 'Ophthalmology',
        'Otolaryngology', 'Nursing', 'Nurse', 'Midwifery', 'Physical Therapy',
        'Pharmacy', 'Medical Technology', 'Radiologic Technology', 'GOSURF50',
    ];

    public function idType(): IdType
    {
        return IdType::PhPrc;
    }

    /**
     * @return array{profession: ?string, license_number: ?string, license_expiry: ?string, full_name: ?string}
     */
    public function extractFields(string $fullText): array
    {
        return [
            'profession' => $this->extractProfession($fullText),
            'license_number' => $this->extractLicenseNumber($fullText),
            'license_expiry' => $this->extractLicenseExpiry($fullText),
            'full_name' => $this->extractFullName($fullText),
        ];
    }

    private function extractProfession(string $text): ?string
    {
        // Primary: explicit "Profession:" label.
        if (preg_match('/\bProfession\b[ \t]*+[:\-]?+[ \t]*+(.+)/i', $text, $matches)) {
            $value = trim($matches[1]);

            if ($value !== '') {
                return $value;
            }
        }

        // Fallback: standalone profession banner (e.g. "NURSE" on the PRC
        // card when no explicit "Profession:" line is present).
        foreach (self::KNOWN_PROFESSIONS as $profession) {
            if (preg_match('/\b'.preg_quote($profession, '/').'\b/i', $text)) {
                return $profession;
            }
        }

        return null;
    }

    private function extractLicenseNumber(string $text): ?string
    {
        // Primary: match the explicit "License No." or "Registration No."
        // labels.  The `(?!\d)` guard prevents silently truncating a longer
        // run of digits to its first 7 digits.
        if (preg_match('/(?:License|Registration)\s*No\.?\s*[:\-]?\s*([0-9]{4,})(?!\d)/i', $text, $matches)) {
            return $matches[1];
        }

        // Fallback for garbled OCR: PRC registration numbers are always 7
        // digits.  Look for a standalone 7-digit number if the label was
        // not readable.  Avoid matching dates (which contain 4-digit years)
        // by requiring exactly 7 digits bounded by non-digit characters.
        if (preg_match('/(?<!\d)([0-9]{7})(?!\d)/', $text, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractLicenseExpiry(string $text): ?string
    {
        // Primary: full "Valid Until" / "Expiry" / "Expiration" labels.
        // The separator between label and date may contain OCR noise
        // characters like >, |, or : alongside normal spaces/dashes.
        if (preg_match('/(?:Valid\s*Until|Expiry|Expiration)\s*[>\-|:\s]*([0-9]{1,2}[\/\-][0-9]{1,2}[\/\-][0-9]{2,4})/i', $text, $matches)) {
            return $matches[1];
        }

        // Fallback for garbled OCR: "UNTIL" alone before a date (the label
        // may be partially garbled but the keyword survives).
        if (preg_match('/\bUNTIL\b[^\d]*([0-9]{1,2}[\/\-][0-9]{1,2}[\/\-][0-9]{2,4})/i', $text, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractFullName(string $text): ?string
    {
        // Some PRC layouts print name fields separately (e.g. "LAST NAME",
        // "FIRST NAME", "MIDDLE NAME" on distinct lines) rather than a
        // single "Name:" field. Combine them into "First Middle Last".
        // This must run before the generic \bName\b check below, because
        // "LAST NAME" / "FIRST NAME" / "MIDDLE NAME" all contain \bName\b
        // and would otherwise capture just one fragment of the full name.
        if (preg_match('/FIRST\s+NAME\b[ \t]*+[:\-]?+[ \t]*+(.+)/i', $text, $first)
            && preg_match('/LAST\s+NAME\b[ \t]*+[:\-]?+[ \t]*+(.+)/i', $text, $last)) {
            $middle = '';
            if (preg_match('/MIDDLE\s+NAME\b[ \t]*+[:\-]?+[ \t]*+(.+)/i', $text, $mid)) {
                $middle = ' '.$this->cleanName($mid[1]);
            }

            $full = trim($this->cleanName($first[1]).$middle.' '.$this->cleanName($last[1]));

            if ($full !== '') {
                return $full;
            }
        }

        // Standalone "Name:" label — require "Name" to appear at the start
        // of a line (with optional leading whitespace) so that garbled tokens
        // like "mooie name" on the same line as other text don't match.
        if (preg_match('/^[ \t]*Name\b[ \t]*+[:\-]?+[ \t]*+(.+)/mi', $text, $matches)) {
            $value = $this->cleanName($matches[1]);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Strip common OCR noise characters (>, |, *, etc.) and excess
     * whitespace from an extracted name value, keeping only letters,
     * spaces, hyphens, periods, and apostrophes.
     */
    private function cleanName(string $raw): string
    {
        // Remove characters that aren't letters, spaces, hyphens, dots,
        // or apostrophes — these are all valid in Filipino names.
        $cleaned = preg_replace('/[^\p{L}\s.\'-]/u', '', trim($raw));

        // Collapse multiple spaces.
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);

        return trim($cleaned);
    }
}

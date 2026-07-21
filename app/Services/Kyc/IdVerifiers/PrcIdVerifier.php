<?php

namespace App\Services\Kyc\IdVerifiers;

use App\Contracts\Kyc\IdVerifierContract;
use App\Enums\IdType;

class PrcIdVerifier implements IdVerifierContract
{
    /**
     * Curated list of PRC profession titles that may appear as a standalone
     * banner or as the value of a "Profession:" line. Includes GOSURF50 - not
     * a real profession, it's the promo text printed on a specific fake PRC
     * ID used for testing this pipeline end-to-end (deliberately kept, not
     * OCR noise - see conversation history if this looks out of place again).
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

    /**
     * Labels this card layout may print in a column, all together, with
     * their values listed afterward in the same order in a second column
     * (rather than each label sharing a line with its own value) - this is
     * how Google Cloud Vision's DOCUMENT_TEXT_DETECTION reads some PRC card
     * scans, since it groups text by visual column rather than row.
     *
     * @var array<int, string>
     */
    private const COLUMN_LABELS = [
        'LAST NAME', 'FIRST NAME', 'MIDDLE NAME',
        'REGISTRATION NO.', 'REGISTRATION DATE', 'VALID UNTIL',
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

        // Fallback: column-layout value (see extractLabelValueBlock) may
        // still carry a leading OCR bullet glyph (e.g. "► 10/01/2022"), so
        // pull just the date substring rather than assuming a clean value.
        $value = $this->extractLabelValueBlock($text)['VALID UNTIL'] ?? null;

        if ($value !== null && preg_match('/([0-9]{1,2}[\/\-][0-9]{1,2}[\/\-][0-9]{2,4})/', $value, $matches)) {
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

        // Fallback: column layout (see extractLabelValueBlock) - labels and
        // values sit in two separate blocks rather than sharing a line.
        $block = $this->extractLabelValueBlock($text);

        if (isset($block['LAST NAME'], $block['FIRST NAME'])) {
            $middle = isset($block['MIDDLE NAME']) ? ' '.$this->cleanName($block['MIDDLE NAME']) : '';
            $full = trim($this->cleanName($block['FIRST NAME']).$middle.' '.$this->cleanName($block['LAST NAME']));

            if ($full !== '') {
                return $full;
            }
        }

        return null;
    }

    /**
     * Some PRC card scans get read by Vision column-by-column rather than
     * row-by-row: every label appears first, then every value in the same
     * order, as two separate blocks - e.g.
     *   LAST NAME
     *   FIRST NAME
     *   VALID UNTIL
     *   Dela Cruz
     *   Juan
     *   10/01/2022
     * Detects a contiguous run of recognized COLUMN_LABELS lines and maps
     * each to the value at the same offset in the following lines.
     *
     * @return array<string, string> label => value, using the canonical
     *                               COLUMN_LABELS spelling as the key
     */
    private function extractLabelValueBlock(string $text): array
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $text)), fn (string $line) => $line !== ''));

        foreach ($lines as $startIndex => $line) {
            $labels = [];

            for ($i = $startIndex; $i < count($lines); $i++) {
                $matchedLabel = null;

                foreach (self::COLUMN_LABELS as $label) {
                    if (preg_match('/^'.preg_quote($label, '/').'\.?$/i', $lines[$i])) {
                        $matchedLabel = $label;
                        break;
                    }
                }

                if ($matchedLabel === null) {
                    break;
                }

                $labels[] = $matchedLabel;
            }

            // Require at least 2 consecutive recognized labels before
            // trusting this as a column block, so a single incidental
            // label-shaped line elsewhere in the text doesn't misfire.
            if (count($labels) < 2) {
                continue;
            }

            $valuesStart = $startIndex + count($labels);

            if ($valuesStart + count($labels) > count($lines)) {
                continue;
            }

            $values = array_slice($lines, $valuesStart, count($labels));

            return array_combine($labels, $values);
        }

        return [];
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

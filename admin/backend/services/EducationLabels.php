<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Canonical human labels for the education vocabularies (M2).
 *
 * Producers:
 *  - HR register select + info sheet-edit select + Telegram form registry all
 *    store the same 12 education codes (kg … other) and 14 spiritual grades.
 *  - CSV/Excel import stores the cell text verbatim (no enum on the column),
 *    so labels like "High School" or specifics like "primary 3" also exist.
 *
 * Renderers must show a proper label for known codes ("tvet_level1" is not a
 * label) while NEVER rewriting or dropping unknown text — falling back to the
 * stored value verbatim keeps imported specifics intact. Callers escape the
 * returned string for HTML (see H2).
 */
final class EducationLabels
{
    private const EDUCATION = [
        'kg' => 'KG',
        'elementary' => 'Elementary (1–8)',
        'high' => 'High School (9–12)',
        'tvet_level1' => 'TVET Level I',
        'tvet_level2' => 'TVET Level II',
        'tvet_level3' => 'TVET Level III',
        'diploma' => 'Diploma',
        'advanced_diploma' => 'Advanced Diploma',
        'degree' => 'Degree',
        'masters' => 'Masters',
        'phd' => 'PhD',
        'other' => 'ሌላ / Other',
    ];

    private const SPIRITUAL = [
        'grade_1' => '1ኛ ክፍል / Grade 1',
        'grade_2' => '2ኛ ክፍል / Grade 2',
        'grade_3' => '3ኛ ክፍል / Grade 3',
        'grade_4' => '4ኛ ክፍል / Grade 4',
        'grade_5' => '5ኛ ክፍል / Grade 5',
        'grade_6' => '6ኛ ክፍል / Grade 6',
        'grade_7' => '7ኛ ክፍል / Grade 7',
        'grade_8' => '8ኛ ክፍል / Grade 8',
        'grade_9' => '9ኛ ክፍል / Grade 9',
        'grade_10' => '10ኛ ክፍል / Grade 10',
        'grade_11' => '11ኛ ክፍል / Grade 11',
        'grade_12' => '12ኛ ክፍል / Grade 12',
        'diploma' => 'ዲፕሎማ / Diploma',
        'degree' => 'ዲግሪ / Degree',
    ];

    /** Canonical code => label map (filter dropdowns + service enum). */
    public static function options(): array
    {
        return self::EDUCATION;
    }

    /** Canonical codes accepted by the directory filter. */
    public static function codes(): array
    {
        return array_keys(self::EDUCATION);
    }

    public static function education(?string $stored): string
    {
        $stored = trim((string)$stored);
        if ($stored === '') return '—';
        // Case-insensitive code match ("High" typed by hand still labels);
        // anything else is preserved verbatim (imported specifics, labels).
        $lower = strtolower(str_replace(' ', '_', $stored));
        return self::EDUCATION[$lower] ?? $stored;
    }

    public static function spiritual(?string $stored): string
    {
        $stored = trim((string)$stored);
        if ($stored === '') return '—';
        $lower = strtolower(str_replace(' ', '_', $stored));
        return self::SPIRITUAL[$lower] ?? $stored;
    }
}

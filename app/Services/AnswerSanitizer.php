<?php

namespace App\Services;

class AnswerSanitizer
{
    /**
     * Normalizes an answer for storage/duplicate-comparison while still
     * allowing natural phrase punctuation (apostrophes, hyphens, periods,
     * commas, colons, ampersands, accented letters) instead of stripping
     * every non-alphanumeric character. Purely decorative characters (like
     * "!" or "?" padding used to dodge duplicate detection) are still removed,
     * and stray punctuation is trimmed from the ends of the answer.
     */
    public function sanitize(string $raw): string
    {
        $normalized = str_replace(['’', '‘', '–', '—'], ["'", "'", '-', '-'], trim($raw));
        $lower = mb_strtolower($normalized);
        $allowed = preg_replace('/[^\p{L}\p{N} \'\-.,:&]/u', '', $lower);
        $collapsed = preg_replace('/\s+/', ' ', $allowed);
        $edgesTrimmed = preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $collapsed);

        return trim($edgesTrimmed);
    }
}

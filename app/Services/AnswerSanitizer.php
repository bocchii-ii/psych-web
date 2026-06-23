<?php

namespace App\Services;

class AnswerSanitizer
{
    public function sanitize(string $raw): string
    {
        $lower = mb_strtolower(trim($raw));
        $stripped = preg_replace('/[^a-z0-9 ]/u', '', $lower);

        return preg_replace('/\s+/', ' ', $stripped);
    }
}

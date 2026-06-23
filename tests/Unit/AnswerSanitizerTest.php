<?php

namespace Tests\Unit;

use App\Services\AnswerSanitizer;
use PHPUnit\Framework\TestCase;

class AnswerSanitizerTest extends TestCase
{
    private AnswerSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new AnswerSanitizer;
    }

    public function test_lowercases_all_characters(): void
    {
        $this->assertSame('hello world', $this->sanitizer->sanitize('HELLO WORLD'));
    }

    public function test_trims_leading_and_trailing_whitespace(): void
    {
        $this->assertSame('hello', $this->sanitizer->sanitize('  hello  '));
    }

    public function test_strips_special_characters_except_spaces(): void
    {
        $this->assertSame('hello world', $this->sanitizer->sanitize('hello, world!'));
    }

    public function test_preserves_numbers(): void
    {
        $this->assertSame('area 51', $this->sanitizer->sanitize('area 51'));
    }

    public function test_strips_parentheses_and_commas(): void
    {
        $this->assertSame('k2 8611 m', $this->sanitizer->sanitize('K2 (8,611 m)'));
    }

    public function test_collapses_multiple_spaces_into_one(): void
    {
        $this->assertSame('hello world', $this->sanitizer->sanitize('hello   world'));
    }

    public function test_strips_accented_characters(): void
    {
        $this->assertSame('nave caf', $this->sanitizer->sanitize('naïve café'));
    }

    public function test_returns_empty_string_for_only_special_characters(): void
    {
        $this->assertSame('', $this->sanitizer->sanitize('!!!???'));
    }

    public function test_handles_mixed_numbers_letters_and_specials(): void
    {
        $this->assertSame('route 66', $this->sanitizer->sanitize('Route 66!'));
    }

    public function test_passes_through_already_clean_input(): void
    {
        $this->assertSame('paris', $this->sanitizer->sanitize('paris'));
    }
}

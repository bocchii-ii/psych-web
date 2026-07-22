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

    public function test_strips_decorative_punctuation_but_keeps_commas(): void
    {
        $this->assertSame('hello, world', $this->sanitizer->sanitize('hello, world!'));
    }

    public function test_preserves_numbers(): void
    {
        $this->assertSame('area 51', $this->sanitizer->sanitize('area 51'));
    }

    public function test_strips_parentheses_but_keeps_commas(): void
    {
        $this->assertSame('k2 8,611 m', $this->sanitizer->sanitize('K2 (8,611 m)'));
    }

    public function test_collapses_multiple_spaces_into_one(): void
    {
        $this->assertSame('hello world', $this->sanitizer->sanitize('hello   world'));
    }

    public function test_preserves_accented_characters(): void
    {
        $this->assertSame('naïve café', $this->sanitizer->sanitize('naïve café'));
    }

    public function test_returns_empty_string_for_only_special_characters(): void
    {
        $this->assertSame('', $this->sanitizer->sanitize('!!!???'));
    }

    public function test_strips_decorative_punctuation(): void
    {
        $this->assertSame('route 66', $this->sanitizer->sanitize('Route 66!'));
    }

    public function test_passes_through_already_clean_input(): void
    {
        $this->assertSame('paris', $this->sanitizer->sanitize('paris'));
    }

    public function test_preserves_apostrophes_in_contractions(): void
    {
        // The trailing apostrophe is trimmed as edge punctuation, same as
        // the leading/trailing dots and bangs in test_trims_stray_punctuation_from_the_edges.
        $this->assertSame("don't stop believin", $this->sanitizer->sanitize("Don't Stop Believin'"));
    }

    public function test_normalizes_curly_apostrophes_to_straight(): void
    {
        $this->assertSame("it's a trap", $this->sanitizer->sanitize('It’s a trap'));
    }

    public function test_preserves_hyphens_in_compound_words(): void
    {
        $this->assertSame('spider-man: no way home', $this->sanitizer->sanitize('Spider-Man: No Way Home'));
    }

    public function test_normalizes_em_and_en_dashes_to_hyphen(): void
    {
        $this->assertSame('spider-man', $this->sanitizer->sanitize('Spider—Man'));
    }

    public function test_preserves_periods_and_colons_in_phrases(): void
    {
        $this->assertSame('mr. robot', $this->sanitizer->sanitize('Mr. Robot'));
    }

    public function test_preserves_ampersands(): void
    {
        $this->assertSame('fast & furious', $this->sanitizer->sanitize('Fast & Furious'));
    }

    public function test_trims_stray_punctuation_from_the_edges(): void
    {
        $this->assertSame('hello world', $this->sanitizer->sanitize('...hello world!!!'));
    }

    public function test_handles_full_sentence_answers(): void
    {
        $this->assertSame(
            "here's looking at you, kid",
            $this->sanitizer->sanitize("Here's looking at you, kid."),
        );
    }
}

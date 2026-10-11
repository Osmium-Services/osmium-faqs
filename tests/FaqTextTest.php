<?php

declare(strict_types=1);

namespace Tests\Services\Faqs;

use Osmium\Services\Faqs\Models\FaqText;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/FaqText.php';

/**
 * The rules a question and answer must meet, and how an answer becomes safe HTML. The same rules apply in the
 * admin editor and to anything written over MCP.
 */
final class FaqTextTest extends TestCase
{
    public function test_a_question_and_answer_are_trimmed(): void
    {
        $this->assertSame(['question' => 'Why?', 'answer' => 'Because.'], FaqText::cleanPair("  Why?\n", "\n Because. "));
    }

    public function test_a_missing_question_or_answer_is_refused_with_a_clear_message(): void
    {
        try {
            FaqText::cleanPair('   ', 'Answer');
            $this->fail('A blank question was accepted');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Every answer needs a question', $e->getMessage());
        }

        try {
            FaqText::cleanPair('Why?', "  \n ");
            $this->fail('A blank answer was accepted');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Every question needs an answer: Why?', $e->getMessage());
        }
    }

    public function test_the_length_limits_allow_the_limit_itself_and_refuse_one_more(): void
    {
        $this->assertSame(500, \mb_strlen(FaqText::cleanPair(\str_repeat('q', 500), 'a')['question']));
        $this->assertSame(10000, \mb_strlen(FaqText::cleanPair('q', \str_repeat('a', 10000))['answer']));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('500 characters');
        FaqText::cleanPair(\str_repeat('q', 501), 'a');
    }

    public function test_an_answer_over_10000_characters_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('10,000 characters');
        FaqText::cleanPair('q', \str_repeat('a', 10001));
    }

    public function test_the_limits_count_characters_not_bytes(): void
    {
        $this->assertSame(500, \mb_strlen(FaqText::cleanPair(\str_repeat('é', 500), 'a')['question']));
    }

    public function test_an_answer_becomes_escaped_paragraphs_with_line_breaks_kept(): void
    {
        $this->assertSame('<p>One<br>two</p><p>Three</p>', FaqText::html("One\ntwo\n\nThree"));
        $this->assertSame('<p>A</p><p>B</p>', FaqText::html("A\r\n\r\nB"));
    }

    public function test_markup_in_an_answer_is_shown_as_text_and_cannot_run(): void
    {
        $html = FaqText::html('<script>alert(1)</script> & "x"');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&amp;', $html);
    }

    public function test_blank_text_gives_no_html(): void
    {
        $this->assertSame('', FaqText::html(''));
        $this->assertSame('', FaqText::html("  \n\n  "));
    }

    public function test_a_list_name_becomes_a_lowercase_hyphenated_slug(): void
    {
        $this->assertSame('delivery-returns', FaqText::slug(' Delivery & Returns! '));
        $this->assertSame('', FaqText::slug('!!!'));
        $this->assertSame('a-b-2', FaqText::slug('A / B 2'));
    }
}

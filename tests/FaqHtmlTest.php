<?php

declare(strict_types=1);

namespace Tests\Services\Faqs;

use Osmium\Services\Faqs\Models\FaqHtml;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/FaqText.php';
require_once __DIR__ . '/../models/FaqHtml.php';

/**
 * What an FAQ answer may hold: a short list of tags, links only to safe addresses.
 */
final class FaqHtmlTest extends TestCase
{
    public function test_allowed_markup_is_kept(): void
    {
        $html = '<p>Read <strong>this</strong> and <em>that</em>.</p><ul><li>One</li><li>Two</li></ul><ol><li>First</li></ol><p>Line<br>break</p>';

        $this->assertSame($html, FaqHtml::clean($html));
    }

    public function test_bold_and_italic_tags_are_renamed(): void
    {
        $this->assertSame('<p><strong>a</strong> <em>b</em></p>', FaqHtml::clean('<p><b>a</b> <i>b</i></p>'));
    }

    public function test_a_site_link_keeps_its_address_only(): void
    {
        $out = FaqHtml::clean('<p><a href="/getting-rid-of-foxes/" class="x" style="color:red" onclick="evil()" target="_blank">foxes</a></p>');

        $this->assertSame('<p><a href="/getting-rid-of-foxes/">foxes</a></p>', $out);
    }

    public function test_an_external_link_gets_noopener(): void
    {
        $out = FaqHtml::clean('<p><a href="https://example.com/a?b=1&amp;c=2">x</a></p>');

        $this->assertSame('<p><a href="https://example.com/a?b=1&amp;c=2" rel="noopener noreferrer">x</a></p>', $out);
    }

    /**
     * @dataProvider unsafeAddresses
     */
    public function test_a_link_to_an_unsafe_address_is_unwrapped_to_its_text(string $href): void
    {
        $out = FaqHtml::clean('<p><a href="' . $href . '">click</a></p>');

        $this->assertSame('<p>click</p>', $out);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeAddresses(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'javascript mixed case' => ['JaVaScRiPt:alert(1)'],
            'data' => ['data:text/html;base64,AAAA'],
            'protocol relative' => ['//evil.example/x'],
            'relative without slash' => ['page.html'],
            'with a space' => ['/a b'],
            'tab hidden in scheme' => ["java\tscript:alert(1)"],
            'empty' => [''],
        ];
    }

    public function test_scripts_styles_and_frames_are_removed_with_their_content(): void
    {
        $out = FaqHtml::clean('<p>Hi</p><script>alert(1)</script><style>p{}</style><iframe src="https://x"></iframe><p>there</p>');

        $this->assertSame('<p>Hi</p><p>there</p>', $out);
    }

    public function test_event_handlers_and_unknown_attributes_never_survive(): void
    {
        $out = FaqHtml::clean('<p onmouseover="x()" style="a:b" class="c" id="d">text</p><img src="x" onerror="y()">');

        $this->assertSame('<p>text</p>', $out);
    }

    public function test_other_tags_are_unwrapped_and_their_text_kept(): void
    {
        $this->assertSame('<p>one two</p>', FaqHtml::clean('<p><span style="x">one</span> <font color="red">two</font></p>'));
    }

    public function test_blocks_and_headings_become_paragraphs(): void
    {
        $this->assertSame('<p>Title</p><p>Body</p>', FaqHtml::clean('<h2>Title</h2><div>Body</div>'));
    }

    public function test_loose_text_is_wrapped_in_a_paragraph_and_escaped(): void
    {
        $this->assertSame('<p>a &lt; b &amp; c</p>', FaqHtml::clean('a &lt; b &amp; c'));
    }

    public function test_empty_paragraphs_and_blank_input_give_nothing(): void
    {
        $this->assertSame('', FaqHtml::clean(''));
        $this->assertSame('', FaqHtml::clean("  \n "));
        $this->assertSame('', FaqHtml::clean('<p></p><p><br></p>'));
    }

    public function test_text_alone_is_not_turned_into_markup(): void
    {
        $out = FaqHtml::clean('<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>');

        $this->assertSame('<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>', $out);
    }

    public function test_the_plain_text_version_separates_paragraphs_and_lists(): void
    {
        $text = FaqHtml::toText('<p>First <strong>bold</strong> &amp; more</p><p>Second<br>line</p><ul><li>One</li><li>Two</li></ul>');

        $this->assertSame("First bold & more\n\nSecond\nline\n\n- One\n- Two", $text);
    }

    public function test_a_plain_text_answer_is_escaped_and_never_trusted_as_html(): void
    {
        $out = FaqHtml::render("One <b>x</b>\n\nTwo", false);

        $this->assertSame('<p>One &lt;b&gt;x&lt;/b&gt;</p><p>Two</p>', $out);
    }

    public function test_an_html_answer_is_cleaned_again_when_it_is_printed(): void
    {
        $out = FaqHtml::render('<p>ok</p><script>x()</script><p><a href="javascript:x()">no</a></p>', true);

        $this->assertSame('<p>ok</p><p>no</p>', $out);
    }

    public function test_plain_text_of_a_saved_answer_depends_on_how_it_was_saved(): void
    {
        $this->assertSame('Read the guide', FaqHtml::plain('<p>Read the <a href="/g/">guide</a></p>', true));
        $this->assertSame('Typed <b> text', FaqHtml::plain("  Typed <b> text \n", false));
    }
}

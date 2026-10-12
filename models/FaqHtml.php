<?php

declare(strict_types=1);

namespace Osmium\Services\Faqs\Models;

/**
 * The only HTML an FAQ answer may hold: paragraphs, line breaks, bold, italic, lists and links. Everything
 * else is unwrapped (its text is kept) and scripts, styles and the like are removed with their content.
 * A link keeps only its address, and only if it is a site path, http(s) or mailto. Pure.
 */
class FaqHtml
{
    /** Tags that print as themselves */
    private const KEEP = ['p', 'br', 'strong', 'em', 'ul', 'ol', 'li'];

    /** Tags renamed to the one we keep */
    private const RENAME = ['b' => 'strong', 'i' => 'em'];

    /** Tags removed together with everything inside them */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'noscript', 'textarea', 'select', 'button', 'form', 'svg', 'math'];

    /** Tags that start a new paragraph when unwrapped */
    private const BLOCKS = ['div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'table', 'tr'];

    /**
     * A stored answer as safe HTML: an HTML answer is cleaned again on the way out, a plain-text one is escaped
     * with blank-line paragraphs.
     */
    public static function render(string $answer, bool $isHtml): string
    {
        return $isHtml ? self::clean($answer) : FaqText::html($answer);
    }

    /**
     * A stored answer as plain text, whichever way it was saved.
     */
    public static function plain(string $answer, bool $isHtml): string
    {
        return $isHtml ? self::toText($answer) : \trim($answer);
    }

    public static function clean(string $html): string
    {
        $trimmed = \trim($html);
        if ($trimmed === '') return '';

        $document = new \DOMDocument();
        $previous = \libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><body>' . $trimmed . '</body>', \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD | \LIBXML_NONET);
        \libxml_clear_errors();
        \libxml_use_internal_errors($previous);

        $body = $document->getElementsByTagName('body')->item(0);
        if ($body === null) return '';

        $out = self::children($body);
        $out = \preg_replace('#<(p|li)>(\s|<br>)*</\1>#', '', $out) ?? '';
        $hasBlock = \preg_match('#<(p|ul|ol)[ >]#', $out) === 1;
        if (!$hasBlock && \trim($out) !== '') $out = '<p>' . $out . '</p>'; // Loose text becomes a paragraph

        return \trim($out);
    }

    /**
     * The plain text of an answer: paragraphs separated by a blank line, list items by a line, tags gone.
     */
    public static function toText(string $html): string
    {
        $withBreaks = \preg_replace(['#<br\s*/?>#i', '#</p>#i', '#</li>#i', '#<li>#i'], ["\n", "\n\n", "\n", '- '], self::clean($html)) ?? '';
        $text = \html_entity_decode(\strip_tags($withBreaks), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        return \trim(\preg_replace("/\n{3,}/", "\n\n", $text) ?? '');
    }

    /**
     * The address if it is safe to link to, else null: a site path, http(s) or mailto.
     */
    public static function safeUrl(string $url): ?string
    {
        $trimmed = \trim($url);
        if ($trimmed === '' || \preg_match('/[\x00-\x1f\x7f\s]/', $trimmed) === 1) return null;

        $isSitePath = \str_starts_with($trimmed, '/') && !\str_starts_with($trimmed, '//');
        $isAllowedScheme = \preg_match('#^(https?://|mailto:)\S+#i', $trimmed) === 1;

        return $isSitePath || $isAllowedScheme ? $trimmed : null;
    }

    private static function children(\DOMNode $node): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            $out .= self::node($child);
        }

        return $out;
    }

    private static function node(\DOMNode $node): string
    {
        if ($node instanceof \DOMText) return \htmlspecialchars($node->nodeValue ?? '', \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
        if (!$node instanceof \DOMElement) return '';

        $tag = \strtolower($node->tagName);
        $tag = self::RENAME[$tag] ?? $tag;

        if (\in_array($tag, self::DROP, true)) return '';
        if ($tag === 'br') return '<br>';
        if ($tag === 'a') return self::link($node);
        if (\in_array($tag, self::KEEP, true)) return "<{$tag}>" . self::children($node) . "</{$tag}>";
        if (\in_array($tag, self::BLOCKS, true)) return self::unwrapBlock($node);

        return self::children($node); // span, font, unknown tags: keep the text
    }

    private static function unwrapBlock(\DOMElement $node): string
    {
        $inner = self::children($node);
        $isBlockInside = \preg_match('#<(p|ul|ol)[ >]#', $inner) === 1;

        return $isBlockInside ? $inner : '<p>' . $inner . '</p>';
    }

    private static function link(\DOMElement $node): string
    {
        $inner = self::children($node);
        $url = self::safeUrl($node->getAttribute('href'));
        if ($url === null) return $inner;

        $external = \preg_match('#^https?://#i', $url) === 1;
        $rel = $external ? ' rel="noopener noreferrer"' : '';

        return '<a href="' . \htmlspecialchars($url, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8') . '"' . $rel . '>' . $inner . '</a>';
    }
}

<?php

declare(strict_types=1);

namespace Osmium\Services\Faqs\Models;

/**
 * Plain-text helpers for FAQ content. Pure.
 */
class FaqText
{
    /**
     * Escaped text with a blank line starting a new paragraph and a single line break kept as <br>
     */
    public static function html(string $answer): string
    {
        $trimmed = \trim($answer);
        $paragraphs = \preg_split('/\R{2,}/', $trimmed) ?: [];
        $html = '';
        foreach ($paragraphs as $paragraph) {
            $text = \trim($paragraph);
            $isBlank = $text === '';
            if ($isBlank) continue;

            $escaped = \htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
            $withBreaks = \preg_replace('/\R/', '<br>', $escaped);
            $html .= '<p>' . $withBreaks . '</p>';
        }

        return $html;
    }

    /**
     * Lowercase letters, digits and single hyphens, for the slug of a list
     */
    public static function slug(string $text): string
    {
        $trimmed = \trim($text);
        $lower = \strtolower($trimmed);
        $slug = \preg_replace('/[^a-z0-9]+/', '-', $lower) ?? '';

        return \trim($slug, '-');
    }
}

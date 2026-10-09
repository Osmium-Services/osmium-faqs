<?php

declare(strict_types=1);

namespace Osmium\Services\Faqs\Models;

/**
 * Builds the FAQPage JSON-LD for a list's items, so a theme prints one tag and never assembles schema by hand.
 */
class FaqSchema
{
    /**
     * @param array<int, array{question: string, answer: string}> $items from Faqs::items()
     * @return string a script tag, or '' when there are no items (an empty FAQPage is invalid)
     */
    public static function script(array $items): string
    {
        if (!$items) return '';

        $questions = [];
        foreach ($items as $item) {
            $questions[] = [
                '@type' => 'Question',
                'name' => (string) $item['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => FaqText::html((string) $item['answer'])],
            ];
        }

        $schema = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $questions];

        // HEX_TAG keeps an answer containing "</script>" from ending the tag early
        $json = \json_encode($schema, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_HEX_TAG | \JSON_HEX_AMP);

        return '<script type="application/ld+json">' . $json . '</script>';
    }
}

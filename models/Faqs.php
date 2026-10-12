<?php

declare(strict_types=1);

namespace Osmium\Services\Faqs\Models;

use Osmium\Core\Library\OsmiumPDO;

/**
 * What a theme calls: the published questions of a list, by its slug.
 *
 *     $faqs = new \Osmium\Services\Faqs\Models\Faqs($this->osmium->dataSource);
 *     $items = $faqs->items('delivery');
 *
 * Each item is an array with id, anchor (a safe element id), question, answer (the answer as plain text) and
 * answer_html (the answer as safe HTML: paragraphs, line breaks, bold, italic, lists and links), so a theme can print
 * either. An unknown or deleted list gives an empty array.
 */
class Faqs
{
    public function __construct(private OsmiumPDO $database)
    {
    }

    /**
     * @return array<int, array{id: int, anchor: string, question: string, answer: string, answer_html: string}>
     */
    public function items(string $slug): array
    {
        $list = (new FaqList($this->database))->findBySlug($slug);
        if (!$list) return [];

        $items = [];
        foreach ((new FaqItem($this->database))->publishedForList((int) $list['id']) as $row) {
            $items[] = [
                'id' => (int) $row['id'],
                'anchor' => 'faq-' . (int) $row['id'],
                'question' => (string) $row['question'],
                'answer' => FaqHtml::plain((string) $row['answer'], (bool) ($row['is_html'] ?? false)),
                'answer_html' => FaqHtml::render((string) $row['answer'], (bool) ($row['is_html'] ?? false)),
            ];
        }

        return $items;
    }
}

<?php

declare(strict_types=1);

namespace Osmium\Services\Faqs\Models;

/**
 * What the FAQs service tells the front-end staff bar: that the page printed one of its lists (adminbar.context) and
 * the admin link that goes with it (adminbar.links). A page has no FAQ data of its own, since themes and services fetch
 * a list by slug, so Faqs::items() notes the first list it printed during the request and this reads that.
 * Core asks after the page is built, so the answer is current. Nothing printed means nothing announced.
 */
class FaqAdminBar
{
    /**
     * @param array<string, mixed> $payload the finished page's view data (unused: the list is noted by Faqs::items())
     * @return string|null "faq-list:7" when the page showed a list, null otherwise
     */
    public static function context(array $payload): ?string
    {
        $listId = Faqs::firstShownListId();
        $hasList = $listId !== null && $listId > 0;

        return $hasList ? "faq-list:{$listId}" : null;
    }

    /**
     * @param array{context?: string, role?: string} $payload
     * @return array<int, array<string, mixed>>|null
     */
    public static function links(array $payload): ?array
    {
        $context = (string)($payload['context'] ?? '');

        $isList = \preg_match('/^faq-list:(\d+)$/', $context, $match) === 1;
        if (!$isList) return null;

        return [['label' => 'Edit FAQs', 'icon' => 'bx-help-circle', 'path' => 'faqs/edit/?id=' . (int)$match[1], 'primary' => true, 'order' => 80]];
    }
}

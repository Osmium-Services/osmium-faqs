<?php

declare(strict_types=1);

namespace Osmium\Services\Faqs\Models;

/**
 * What the FAQs service answers when another service asks through a core hook, so the shop can offer a shared list
 * on a product without calling this service's code.
 */
class FaqHooks
{
    /**
     * faq.lists: every live list, for a service's admin to offer as a choice.
     *
     * @param array<string, mixed> $payload dataSource
     * @return array{lists: array<int, array{slug: string, name: string}>}
     */
    public static function lists(array $payload): array
    {
        $lists = [];
        foreach ((new FaqList($payload['dataSource']))->listAll() as $row) {
            $lists[] = ['slug' => (string) $row['slug'], 'name' => (string) $row['name']];
        }

        return ['lists' => $lists];
    }

    /**
     * faq.items: the published questions of one list, as Faqs::items() gives them. Nothing is returned for a blank
     * slug, so a caller with no list chosen gets no answer.
     *
     * @param array<string, mixed> $payload slug, dataSource
     * @return array{items: array<int, array<string, mixed>>}|null
     */
    public static function items(array $payload): ?array
    {
        $slug = \trim((string) ($payload['slug'] ?? ''));
        if ($slug === '') return null;

        return ['items' => (new Faqs($payload['dataSource']))->items($slug)];
    }
}

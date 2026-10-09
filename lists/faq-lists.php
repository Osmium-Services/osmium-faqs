<?php

declare(strict_types=1);

use Osmium\Modules\Admin\Core\ListView;

/**
 * FAQ lists, rendered by Osmium\Modules\Admin\Core\ListView. Rows are shaped by FaqsController::formatList().
 */
return [
    'lists' => [
        'id' => 'faq-lists',
        'title' => 'FAQ Lists',
        'noun' => ['list', 'lists'],
        'endpoint' => 'faqs/action/',
        'emptyText' => 'No FAQ lists yet. Use New List to create the first.',
        'order' => null, // keep the server's name order until a header is clicked
        'columns' => [
            [
                'key' => 'name',
                'label' => 'List',
                'type' => 'text',
                'strong' => true,
                'fill' => true,
                'sub' => ['key' => 'slug', 'style' => 'plain'],
            ],
            ['key' => 'count_label', 'label' => 'Questions', 'type' => 'text', 'nowrap' => true, 'sortKey' => 'item_count'],
            ['key' => 'date_label', 'label' => 'Updated', 'type' => 'text', 'nowrap' => true, 'sortKey' => 'date_sort'],
        ],
        'actions' => [
            ['label' => 'Edit', 'icon' => 'bx-edit-alt', 'url' => '{edit_url}'],
            [
                'label' => 'Delete',
                'icon' => 'bx-trash',
                'action' => 'delete',
                'confirm' => 'Delete this list? It can be restored from Deleted Lists.',
                'danger' => true,
            ],
        ],
    ],

    'deleted' => ListView::deletedPreset(
        definition: [
            'id' => 'faq-lists-deleted',
            'title' => 'Deleted Lists',
            'noun' => ['list', 'lists'],
            'endpoint' => 'faqs/action/',
            'columns' => [
                [
                    'key' => 'name',
                    'label' => 'List',
                    'type' => 'text',
                    'strong' => true,
                    'fill' => true,
                    'sub' => ['key' => 'slug', 'style' => 'plain'],
                ],
                ['key' => 'count_label', 'label' => 'Questions', 'type' => 'text', 'nowrap' => true],
            ],
        ],
        permanentConfirm: 'Permanently delete this list and its questions? This cannot be undone!',
    ),
];

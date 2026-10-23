<?php

declare(strict_types=1);

use Osmium\Modules\Admin\Core\ListView;

/**
 * FAQ lists, rendered by Osmium\Modules\Admin\Core\ListView. Rows are shaped by FaqsController::formatList().
 */
require_once 'app/core/admin/services/ChangedByCell.php';

return [
    'recent' => [
        'id' => 'faq-lists-recent',
        'title' => 'Recent Changes',
        'noun' => ['list', 'lists'],
        'icon' => 'bx-time-five',
        'rowClass' => static fn(array $row): string => empty($row['deleted_at']) ? '' : 'table-danger',
        'endpoint' => 'faqs/action/',
        'datatable' => false,
        'collapsible' => ['label' => 'Recent Changes ({count})'],
        'hideWhenEmpty' => false,
        'columns' => [
            ['key' => 'changed_by', 'label' => '', 'type' => 'html', 'sortable' => false, 'render' => fn(array $row, array $context): string => \Osmium\Modules\Admin\Services\ChangedByCell::html($row, (string)($context['avatarUrl'] ?? ''))],
            [
                'key' => 'name',
                'label' => 'List',
                'type' => 'text',
                'strong' => true,
                'fill' => true,
                'sub' => ['key' => 'slug', 'style' => 'plain'],
            ],
            ['key' => 'count_label', 'label' => 'Questions', 'type' => 'text', 'nowrap' => true],
            ['key' => 'updated_label', 'label' => 'Updated', 'type' => 'text', 'small' => true, 'nowrap' => true],
        ],
    ],

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
            'hideWhenEmpty' => false,
            'columns' => [
                ['key' => 'changed_by', 'label' => '', 'type' => 'html', 'sortable' => false, 'render' => fn(array $row, array $context): string => \Osmium\Modules\Admin\Services\ChangedByCell::html($row, (string)($context['avatarUrl'] ?? ''))],
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

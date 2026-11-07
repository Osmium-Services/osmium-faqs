<?php

declare(strict_types=1);

/**
 * Grid (card) layout for the FAQ Lists list, rendered by Osmium\Modules\Admin\Core\ListViewGrid. Columns use the
 * same vocabulary as lists/faq-lists.php but nothing is shared with it. The actions are a copy of the list's for now.
 */
return [
    'columns' => 4,
    'title' => [
        'key' => 'name',
        'type' => 'text',
        'strong' => true,
        'sub' => ['key' => 'slug', 'style' => 'plain'],
    ],
    'meta' => [
        ['key' => 'count_label', 'type' => 'text'],
        ['key' => 'date_label', 'type' => 'text', 'small' => true],
    ],
    'actionsAt' => 'bottom-end',
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
];

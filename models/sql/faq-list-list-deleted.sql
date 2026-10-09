SELECT l.id, l.name, l.slug, l.deleted_at, COUNT(i.id) AS item_count
FROM {TABLE} l
LEFT JOIN {PREFIX}faq_items i ON i.list_id = l.id
WHERE l.deleted_at IS NOT NULL
GROUP BY l.id, l.name, l.slug, l.deleted_at
ORDER BY l.deleted_at DESC

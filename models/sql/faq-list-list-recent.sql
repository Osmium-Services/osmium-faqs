SELECT l.id, l.name, l.slug, l.created_at, l.updated_at, COUNT(i.id) AS item_count
FROM {TABLE} l
LEFT JOIN {PREFIX}faq_items i ON i.list_id = l.id
WHERE l.deleted_at IS NULL
  AND (l.created_at >= NOW() - INTERVAL 24 HOUR OR l.updated_at >= NOW() - INTERVAL 24 HOUR)
GROUP BY l.id, l.name, l.slug, l.created_at, l.updated_at
ORDER BY GREATEST(COALESCE(l.updated_at, l.created_at), l.created_at) DESC, l.id DESC
LIMIT 10

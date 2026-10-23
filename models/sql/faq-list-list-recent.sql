SELECT l.id, l.name, l.slug, l.created_at, l.updated_at, l.deleted_at, CL.user_name AS changed_by, CU.avatar AS changed_by_avatar, COUNT(i.id) AS item_count
FROM {TABLE} l
LEFT JOIN {PREFIX}faq_items i ON i.list_id = l.id
LEFT JOIN {PREFIX}changelog AS CL ON CL.id = (SELECT MAX(id) FROM {PREFIX}changelog WHERE record_type = 'faq_list' AND record_id = l.id)
LEFT JOIN {PREFIX}users AS CU ON CU.id = CL.user_id
WHERE (l.created_at >= NOW() - INTERVAL 24 HOUR OR l.updated_at >= NOW() - INTERVAL 24 HOUR OR l.deleted_at >= NOW() - INTERVAL 24 HOUR)
GROUP BY l.id, l.name, l.slug, l.created_at, l.updated_at, l.deleted_at, l.deleted_at, CL.user_name, CU.avatar
ORDER BY GREATEST(COALESCE(l.updated_at, l.created_at), l.created_at, COALESCE(l.deleted_at, l.created_at)) DESC, l.id DESC
LIMIT 10

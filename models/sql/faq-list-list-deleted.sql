SELECT l.id, l.name, l.slug, l.deleted_at, CL.user_name AS changed_by, CU.avatar AS changed_by_avatar, COUNT(i.id) AS item_count
FROM {TABLE} l
LEFT JOIN {PREFIX}faq_items i ON i.list_id = l.id
LEFT JOIN {PREFIX}changelog AS CL ON CL.id = (SELECT MAX(id) FROM {PREFIX}changelog WHERE record_type = 'faq_list' AND record_id = l.id)
LEFT JOIN {PREFIX}users AS CU ON CU.id = CL.user_id
WHERE l.deleted_at IS NOT NULL
GROUP BY l.id, l.name, l.slug, l.deleted_at, CL.user_name, CU.avatar
ORDER BY l.deleted_at DESC

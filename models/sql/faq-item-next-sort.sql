SELECT COALESCE(MAX(sort_order), -1) + 1 AS next_sort FROM {TABLE} WHERE list_id = :list_id

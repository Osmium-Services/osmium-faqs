SELECT id, question, answer, is_html FROM {TABLE} WHERE list_id = :list_id AND is_published = 1 ORDER BY sort_order ASC, id ASC

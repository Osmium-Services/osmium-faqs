SELECT id, question, answer, is_html, sort_order, is_published FROM {TABLE} WHERE list_id = :list_id ORDER BY sort_order ASC, id ASC

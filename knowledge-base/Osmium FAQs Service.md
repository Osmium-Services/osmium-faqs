# Osmium FAQs service

Named lists of questions and answers managed in admin, for a theme to show anywhere, with `FAQPage` structured data for
search engines. Repo `Osmium-Services/osmium-faqs`, id `osmium-faqs` (was `faqs` in v1.0.0 and v1.0.1), v1.5.1<!--v:osmium-faqs-->, category
content.

## What it does

- Tables `faq_lists` (name, unique slug, soft delete) and `faq_items` (question, answer, `sort_order`, `is_published`).
- Admin: Content > FAQs (top-level item). The list page uses the shared `ListView` with a deleted bin; the edit page saves the
  **whole list in one request**: items keep their ids, omitted rows are deleted, array order is the sort order. Each edit
  page shows the snippet for its own list.
- No public endpoint and no hook. A theme controller reads the list:

```php
$faqs = new \Osmium\Services\Faqs\Models\Faqs($this->osmium->dataSource);
$items = $faqs->items('your-slug');
// the theme writes the markup, then:
echo \Osmium\Services\Faqs\Models\FaqSchema::script($items);   // FAQPage JSON-LD
```

The PHP namespace stayed `Osmium\Services\Faqs` after the id change.

## Lesson

An admin `edit()` that reads `getStashedParam('id')` must call `$this->osmium->clearStashedQuerystring()` straight after, or
the id sticks in the session and "New List" reopens the last list. The Blog's `edit()` probably has the same pattern
(unverified).

## Tested

Admin CRUD, validation, reorder, a foreign item id, delete/restore/permanent delete, `items()` and the schema output from
the CLI; uninstall and reinstall from GitHub keeps the tables and data (migrations are keyed by service id).

## Not tested

A real theme rendering a list.

## MCP tools (v1.1.0, question add in v1.2.0)

FAQs answers core's `mcp.tools` hook ([[MCP Server]]) with `FaqMcp`: `faq_lists_list` (optionally the bin), and the write tools
`faq_list_delete` and `faq_list_restore`, which only appear when the site sets `mcp.writesEnabled`. Both take the list `id` and its exact
`expected_name` and refuse on a mismatch. They call `FaqBin`, the same class the admin screen uses, so a soft delete keeps the list and its
questions and a restore brings them back. Changelog entries read "<app name> via MCP". No permanent-delete tool, and no tool returns question
or answer text.

`faq_question_add` (v1.2.0, a write tool) adds a question and answer to the end of a list, always **hidden**: nothing goes live until
someone ticks "Shown on the site" in the admin. It takes the list `id` and `expected_name` like the other write tools and checks the
text with `FaqText::cleanPair`, the same rules (question up to 500 characters, answer up to 10,000, both required) the admin editor
now uses. Answers are plain text, escaped on output. There is no tool to edit, publish or remove a question: removing one in the admin
deletes its row for good, so it does not fit the reversible-only rule in [[MCP Server]].

## Shared lists on products (v1.7.0, shop v0.29.0)

A product can pick a FAQs list by slug (`products.faq_list`, shop migration 9) instead of typing its own FAQ text, so similar products share one list. The shop never calls this service's PHP: it asks through the `faq.lists` (admin dropdown) and `faq.items` (product page, AI product record) hooks. The list's questions are turned into the same `h5.accordion` + `div.panel` markup as the product's own text, so themes need no change, and FAQPage schema comes from the list. No list chosen, list empty or deleted, or service not installed: the product's own FAQ text shows. Duplicating a product copies the choice. The dropdown stores the slug, so renaming a list's slug on the FAQs page unlinks products using it (the product edit page shows "(list not found)").

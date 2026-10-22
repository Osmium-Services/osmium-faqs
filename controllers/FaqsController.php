<?php

declare(strict_types=1);

namespace Osmium\Services\Faqs\Controllers;

use Osmium\Modules\Admin\Core\AdminController;
use Osmium\Services\Faqs\Models\FaqBin;
use Osmium\Services\Faqs\Models\FaqItem;
use Osmium\Services\Faqs\Models\FaqList;
use Osmium\Services\Faqs\Models\FaqHtml;
use Osmium\Services\Faqs\Models\FaqText;

/**
 * FAQs admin - the lists, the editor for one list's questions, and the AJAX actions behind them.
 *
 * Routes:
 *   - index()  → /admin/faqs/
 *   - edit()   → /admin/faqs/edit/?id=N   (no id: a new list)
 *   - action() → /admin/faqs/action/      (save, delete, restore, permanently_delete)
 */
class FaqsController extends AdminController
{
    private const RECORD_TYPE = 'faq_list';

    public function index(): void
    {

        $lists = new FaqList($this->osmium->dataSource);

        $this->data['admin']['isAdmin'] = $this->isAdmin();
        $this->data['admin']['faqLists'] = \array_map($this->formatList(...), $lists->listAll());
        $this->data['admin']['recentFaqLists'] = \array_map($this->formatRecentList(...), $lists->listRecent());
        $this->data['admin']['deletedFaqLists'] = \array_map($this->formatList(...), $lists->listDeleted());
        $this->data['admin']['lists'] = require __DIR__ . '/../lists/faq-lists.php';

        $this->setView('faqs/index.phtml');
    }

    public function edit(): void
    {
        $id = (int) $this->osmium->getStashedParam(key: 'id', default: 0); // Core moves query params out of $_GET
        $this->osmium->clearStashedQuerystring(); // Or a later "New List" would reuse this id
        $list = $id ? (new FaqList($this->osmium->dataSource))->getById($id) : null;
        $isMissing = $id && (!$list || $list['deleted_at'] !== null);
        if ($isMissing) $this->redirect('faqs/');

        $items = $id ? (new FaqItem($this->osmium->dataSource))->forList($id) : [];

        $this->data['admin']['faqList'] = [
            'id' => $id,
            'name' => $list['name'] ?? '',
            'slug' => $list['slug'] ?? '',
        ];
        $this->data['admin']['faqItems'] = \array_map($this->formatItem(...), $items);

        $this->setView('faqs/edit.phtml');
    }

    public function action()
    {
        \header('Content-Type: application/json');

        $isPost = $this->isPost();
        if (!$isPost) $this->admin->jsonError('Method not allowed');

        $input = $this->admin->auth->getJsonInput();
        $csrfValid = $this->admin->auth->validateCsrfJson($input);
        if (!$csrfValid) $this->admin->jsonError('Invalid request token. Please refresh and try again.');

        $action = $input['action'] ?? '';
        $id = (int) ($input['id'] ?? 0);

        try {
            $result = match ($action) {
                'save' => $this->save(id: $id, input: $input),
                'delete' => $this->deleteList($id),
                'restore' => $this->restoreList($id),
                'permanently_delete' => $this->permanentlyDeleteList($id),
                default => throw new \InvalidArgumentException('Unknown action'),
            };

            $this->admin->jsonSuccess($result);
        } catch (\Exception $e) {
            $this->admin->jsonError($e->getMessage());
        }
    }

    // =========================================================================
    // Action helpers
    // =========================================================================

    private function save(int $id, array $input): array
    {
        $name = \trim((string) ($input['name'] ?? ''));
        $nameMissing = $name === '';
        if ($nameMissing) throw new \InvalidArgumentException('Name is required');

        $typedSlug = (string) ($input['slug'] ?? '');
        $slug = FaqText::slug($typedSlug !== '' ? $typedSlug : $name);
        $slugMissing = $slug === '';
        if ($slugMissing) throw new \InvalidArgumentException('Slug is required');

        $items = $this->cleanItems($input['items'] ?? []);

        $lists = new FaqList($this->osmium->dataSource);
        $slugTaken = $lists->slugTaken(slug: $slug, excludeId: $id);
        if ($slugTaken) throw new \InvalidArgumentException('Another list (possibly a deleted one) already uses that slug');

        $existing = $id ? $lists->getById($id) : null;
        $isGone = $id && (!$existing || $existing['deleted_at'] !== null);
        if ($isGone) throw new \InvalidArgumentException('List not found');

        $database = $this->osmium->dataSource;
        $database->beginTransaction();
        try {
            $id = $this->saveList(lists: $lists, id: $id, name: $name, slug: $slug);
            $this->saveItems(listId: $id, items: $items);
            $database->endTransaction();
        } catch (\Throwable $e) {
            $database->cancelTransaction();
            throw $e;
        }

        $this->logChange(action: $existing ? 'Updated' : 'Created', id: $id, name: $name);

        return ['success' => true, 'id' => $id];
    }

    private function saveList(FaqList $lists, int $id, string $name, string $slug): int
    {
        if (!$id) return $lists->create(name: $name, slug: $slug);

        $lists->update(id: $id, name: $name, slug: $slug);

        return $id;
    }

    /**
     * Rows keep their id when the editor sent one, new rows are inserted, and rows left out are deleted.
     * The array order is the sort order.
     *
     * @param array<int, array{id: int, question: string, answer: string, is_published: bool}> $items
     */
    private function saveItems(int $listId, array $items): void
    {
        $model = new FaqItem($this->osmium->dataSource);
        $existingIds = \array_map('intval', \array_column($model->forList($listId), 'id'));

        $keptIds = [];
        foreach ($items as $position => $item) {
            $isOwnRow = \in_array($item['id'], $existingIds, true); // An id from another list is saved as a new row
            $item['id'] = $isOwnRow ? $item['id'] : 0;
            $keptIds[] = $this->saveItem(model: $model, listId: $listId, position: $position, item: $item);
        }

        foreach ($existingIds as $existingId) {
            $isKept = \in_array($existingId, $keptIds, true);
            if (!$isKept) $model->delete(id: $existingId, listId: $listId);
        }
    }

    private function saveItem(FaqItem $model, int $listId, int $position, array $item): int
    {
        $isNew = $item['id'] === 0;
        if ($isNew) {
            return $model->insert(
                listId: $listId,
                question: $item['question'],
                answer: $item['answer'],
                sortOrder: $position,
                isPublished: $item['is_published'],
                isHtml: true,
            );
        }

        $model->update(
            id: $item['id'],
            listId: $listId,
            question: $item['question'],
            answer: $item['answer'],
            sortOrder: $position,
            isPublished: $item['is_published'],
            isHtml: true,
        );

        return $item['id'];
    }

    /**
     * Validates the questions an editor sent. A row with neither a question nor an answer is dropped.
     *
     * @return array<int, array{id: int, question: string, answer: string, is_published: bool}>
     */
    private function cleanItems(mixed $rawItems): array
    {
        $notList = !\is_array($rawItems);
        if ($notList) throw new \InvalidArgumentException('Questions are missing');

        $items = [];
        foreach ($rawItems as $raw) {
            $item = $this->cleanItem((array) $raw);
            if ($item) $items[] = $item;
        }

        return $items;
    }

    private function cleanItem(array $raw): ?array
    {
        $question = \trim((string) ($raw['question'] ?? ''));
        $answer = FaqHtml::clean((string) ($raw['answer'] ?? '')); // The editor sends HTML: only the allowed tags are kept
        $isBlank = $question === '' && FaqHtml::toText($answer) === '';
        if ($isBlank) return null;

        $pair = FaqText::cleanPair($question, $answer);

        return [
            'id' => (int) ($raw['id'] ?? 0),
            'question' => $pair['question'],
            'answer' => $pair['answer'],
            'is_published' => !empty($raw['is_published']),
        ];
    }

    /**
     * Soft delete: the list and its questions stay, so a restore brings it all back
     */
    private function deleteList(int $id): array
    {
        $this->bin()->delete($id);

        return ['success' => true];
    }

    private function restoreList(int $id): array
    {
        $this->bin()->restore($id);

        return ['success' => true];
    }

    private function bin(): FaqBin
    {
        return new FaqBin(new FaqList($this->osmium->dataSource), $this->logChange(...));
    }

    /**
     * Admins only, and only from the bin
     */
    private function permanentlyDeleteList(int $id): array
    {
        $notAdmin = !$this->isAdmin();
        if ($notAdmin) throw new \RuntimeException('Permission denied');

        $lists = new FaqList($this->osmium->dataSource);
        $list = $lists->getById($id);
        $isDeleted = $list && $list['deleted_at'] !== null;
        if (!$isDeleted) throw new \InvalidArgumentException('Deleted list not found');

        (new FaqItem($this->osmium->dataSource))->deleteByList($id);
        $lists->delete($id);
        $this->logChange(action: 'Permanently deleted', id: $id, name: (string) $list['name']);

        return ['success' => true];
    }

    // =========================================================================
    // Formatting
    // =========================================================================

    private function formatList(array $list): array
    {
        $count = (int) $list['item_count'];
        $updated = $list['updated_at'] ?? $list['deleted_at'] ?? null;

        return $list + [
            'edit_url' => $this->getBasePath() . 'faqs/edit/?id=' . (int) $list['id'],
            'count_label' => $count . ($count === 1 ? ' question' : ' questions'),
            'date_label' => $updated ? \date('j M Y', \strtotime($updated)) : '',
            'date_sort' => (string) $updated,
        ];
    }

    private function formatRecentList(array $list): array
    {
        $changed = $list['updated_at'] ?? $list['created_at'];

        return $this->formatList($list) + ['updated_label' => \date('j M Y, g:ia', \strtotime((string) $changed))];
    }

    private function formatItem(array $item): array
    {
        return [
            'id' => (int) $item['id'],
            'question' => (string) $item['question'],
            'answer' => FaqHtml::render((string) $item['answer'], (bool) ($item['is_html'] ?? false)), // What the editor shows, as safe HTML
            'isPublished' => (bool) $item['is_published'],
        ];
    }

    private function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    private function logChange(string $action, int $id, string $name): void
    {
        $this->admin->model->changelog->log(
            description: $action . ': FAQ list ' . $name,
            recordType: self::RECORD_TYPE,
            recordId: $id,
        );
    }
}

<?php

declare(strict_types=1);

namespace Osmium\Services\Faqs\Models;

use Osmium\Core\Library\OsmiumPDO;
use Osmium\Core\Models\Model;

/**
 * The questions inside a list. The array order an admin saves is the sort order.
 */
class FaqItem extends Model
{
    protected ?string $sqlDir = __DIR__ . '/sql';

    public function __construct(OsmiumPDO $database)
    {
        parent::__construct(database: $database, tableName: 'faq_items');
    }

    /**
     * Every question in a list, shown or hidden, in order, for the admin editor
     */
    public function forList(int $listId): array
    {
        $this->database->query($this->loadSqlFile('faq-item-list.sql'));
        $this->database->bind(param: ':list_id', value: $listId);

        return $this->database->resultset();
    }

    /**
     * The questions a visitor sees, in order
     */
    public function publishedForList(int $listId): array
    {
        $this->database->query($this->loadSqlFile('faq-item-list-published.sql'));
        $this->database->bind(param: ':list_id', value: $listId);

        return $this->database->resultset();
    }

    public function insert(int $listId, string $question, string $answer, int $sortOrder, bool $isPublished): int
    {
        $this->database->query($this->loadSqlFile('faq-item-insert.sql'));
        $this->database->bind(param: ':list_id', value: $listId);
        $this->bindDetails(question: $question, answer: $answer, sortOrder: $sortOrder, isPublished: $isPublished);
        $this->database->execute();

        return (int) $this->database->lastInsertId();
    }

    /**
     * Adds a question at the end of a list, hidden unless told otherwise
     */
    public function append(int $listId, string $question, string $answer, bool $isPublished = false): int
    {
        $this->database->query($this->loadSqlFile('faq-item-next-sort.sql'));
        $this->database->bind(param: ':list_id', value: $listId);
        $row = $this->database->single();
        $sortOrder = (int) ($row['next_sort'] ?? 0);

        return $this->insert(listId: $listId, question: $question, answer: $answer, sortOrder: $sortOrder, isPublished: $isPublished);
    }

    public function update(
        int $id,
        int $listId,
        string $question,
        string $answer,
        int $sortOrder,
        bool $isPublished,
    ): void {
        $this->database->query($this->loadSqlFile('faq-item-update.sql'));
        $this->database->bind(param: ':id', value: $id);
        $this->database->bind(param: ':list_id', value: $listId);
        $this->bindDetails(question: $question, answer: $answer, sortOrder: $sortOrder, isPublished: $isPublished);
        $this->database->execute();
    }

    public function delete(int $id, int $listId): void
    {
        $this->database->query($this->loadSqlFile('faq-item-delete.sql'));
        $this->database->bind(param: ':id', value: $id);
        $this->database->bind(param: ':list_id', value: $listId);
        $this->database->execute();
    }

    public function deleteByList(int $listId): void
    {
        $this->database->query($this->loadSqlFile('faq-item-delete-by-list.sql'));
        $this->database->bind(param: ':list_id', value: $listId);
        $this->database->execute();
    }

    private function bindDetails(string $question, string $answer, int $sortOrder, bool $isPublished): void
    {
        $this->database->bind(param: ':question', value: $question);
        $this->database->bind(param: ':answer', value: $answer);
        $this->database->bind(param: ':sort_order', value: $sortOrder);
        $this->database->bind(param: ':is_published', value: $isPublished ? 1 : 0);
    }
}

<?php

declare(strict_types=1);

namespace Osmium\Services\Faqs\Models;

use Osmium\Core\Library\OsmiumPDO;
use Osmium\Core\Models\Model;

/**
 * The named FAQ lists an admin manages. A list is soft-deleted first and only removed for good from the bin.
 */
class FaqList extends Model
{
    protected ?string $sqlDir = __DIR__ . '/sql';

    public function __construct(OsmiumPDO $database)
    {
        parent::__construct(database: $database, tableName: 'faq_lists');
    }

    /**
     * Every live list with its question count, by name
     */
    public function listAll(): array
    {
        $this->database->query($this->loadSqlFile('faq-list-list.sql'));

        return $this->database->resultset();
    }

    /**
     * Every list in the bin with its question count, most recently deleted first
     */
    public function listDeleted(): array
    {
        $this->database->query($this->loadSqlFile('faq-list-list-deleted.sql'));

        return $this->database->resultset();
    }

    /**
     * A list whether or not it is deleted (check deleted_at), or null when there is no such list
     */
    public function getById(int $id): ?array
    {
        $this->database->query($this->loadSqlFile('faq-list-get.sql'));
        $this->database->bind(param: ':id', value: $id);
        $list = $this->database->single();

        return $list === false ? null : $list;
    }

    /**
     * The live list with this slug, or null
     */
    public function findBySlug(string $slug): ?array
    {
        $this->database->query($this->loadSqlFile('faq-list-get-by-slug.sql'));
        $this->database->bind(param: ':slug', value: $slug);
        $list = $this->database->single();

        return $list === false ? null : $list;
    }

    /**
     * Whether another list, deleted or not, already uses the slug (the column is unique)
     */
    public function slugTaken(string $slug, int $excludeId = 0): bool
    {
        $this->database->query($this->loadSqlFile('faq-list-slug-taken.sql'));
        $this->database->bind(param: ':slug', value: $slug);
        $this->database->bind(param: ':exclude_id', value: $excludeId);

        return $this->database->single() !== false;
    }

    public function create(string $name, string $slug): int
    {
        $this->database->query($this->loadSqlFile('faq-list-insert.sql'));
        $this->database->bind(param: ':name', value: $name);
        $this->database->bind(param: ':slug', value: $slug);
        $this->database->execute();

        return (int) $this->database->lastInsertId();
    }

    public function update(int $id, string $name, string $slug): void
    {
        $this->database->query($this->loadSqlFile('faq-list-update.sql'));
        $this->database->bind(param: ':id', value: $id);
        $this->database->bind(param: ':name', value: $name);
        $this->database->bind(param: ':slug', value: $slug);
        $this->database->execute();
    }

    public function softDelete(int $id): void
    {
        $this->database->query($this->loadSqlFile('faq-list-soft-delete.sql'));
        $this->database->bind(param: ':id', value: $id);
        $this->database->execute();
    }

    public function restore(int $id): void
    {
        $this->database->query($this->loadSqlFile('faq-list-restore.sql'));
        $this->database->bind(param: ':id', value: $id);
        $this->database->execute();
    }

    public function delete(int $id): void
    {
        $this->database->query($this->loadSqlFile('faq-list-delete.sql'));
        $this->database->bind(param: ':id', value: $id);
        $this->database->execute();
    }
}

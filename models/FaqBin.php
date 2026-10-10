<?php

declare(strict_types=1);

namespace Osmium\Services\Faqs\Models;

/**
 * Soft delete and restore for FAQ lists, shared by the admin screen and the MCP tools so both behave the same.
 * The list and its questions stay, so a restore brings it all back. Permanent delete is deliberately not here:
 * it is admin-only and lives in the controller.
 */
class FaqBin
{
    /** @param \Closure(string, int, string): void $log called with the action ('Deleted', 'Restored'), list id and list name */
    public function __construct(
        private FaqList $lists,
        private \Closure $log,
    ) {}

    /** @return array<string, mixed> the list that was deleted */
    public function delete(int $id): array
    {
        $list = $this->lists->getById($id);
        $isLive = $list && $list['deleted_at'] === null;
        if (!$isLive) throw new \InvalidArgumentException('List not found');

        $this->lists->softDelete($id);
        ($this->log)('Deleted', $id, (string) $list['name']);

        return $list;
    }

    /** @return array<string, mixed> the list that was restored */
    public function restore(int $id): array
    {
        $list = $this->lists->getById($id);
        $isDeleted = $list && $list['deleted_at'] !== null;
        if (!$isDeleted) throw new \InvalidArgumentException('Deleted list not found');

        $this->lists->restore($id);
        ($this->log)('Restored', $id, (string) $list['name']);

        return $list;
    }
}

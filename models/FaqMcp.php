<?php

declare(strict_types=1);

namespace Osmium\Services\Faqs\Models;

use Osmium\Core\Library\McpToolException;
use Osmium\Modules\Admin\Models\Changelog;

/**
 * The FAQs' tools for the /mcp endpoint, offered through core's `mcp.tools` hook: list FAQ lists, read the questions
 * in one, and soft delete or restore a list, and add a question to a list. Delete and restore reuse FaqBin, so they
 * behave exactly like the admin screen, and a new question is checked by the same FaqText::cleanPair rules. A
 * question added here is always hidden: someone publishes it in the admin. There is no permanent delete and no
 * editing or removing of questions. Reading the questions is the only tool that returns question and answer text,
 * and it is read-only.
 */
class FaqMcp
{
    private const RECORD_TYPE = 'faq_list';

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>> tool descriptors
     */
    public static function tools(array $payload): array
    {
        $idAndName = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'The list id from faq_lists_list'],
                'expected_name' => ['type' => 'string', 'description' => "The list's exact name as listed. The call is refused if it does not match the id"],
            ],
            'required' => ['id', 'expected_name'],
        ];

        return [
            [
                'name' => 'faq_lists_list',
                'description' => 'FAQ lists with id, name, slug, number of questions and last updated date. Set deleted to true to list the lists in the bin instead.',
                'inputSchema' => ['type' => 'object', 'properties' => ['deleted' => ['type' => 'boolean', 'description' => 'List deleted lists (the bin)']]],
                'handler' => self::class . '::listLists',
            ],
            [
                'name' => 'faq_questions_list',
                'description' => 'The questions and answers in one FAQ list, in order, each with its id and whether it is shown on the site or hidden. Read-only.',
                'inputSchema' => $idAndName,
                'handler' => self::class . '::questionsList',
            ],
            [
                'name' => 'faq_list_delete',
                'description' => 'Move a FAQ list to the bin (a soft delete: the list and its questions stay). Undo with faq_list_restore.',
                'inputSchema' => $idAndName,
                'write' => true,
                'handler' => self::class . '::delete',
            ],
            [
                'name' => 'faq_question_add',
                'description' => 'Add a question and answer to the end of a FAQ list. It is saved hidden ("Shown on the site" unticked), so nothing goes live until someone ticks it in the admin. Answers are plain text; a blank line starts a new paragraph. Questions can be up to 500 characters and answers up to 10,000.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => $idAndName['properties'] + [
                        'question' => ['type' => 'string', 'description' => 'The question, up to 500 characters'],
                        'answer' => ['type' => 'string', 'description' => 'The answer as plain text, up to 10,000 characters'],
                    ],
                    'required' => ['id', 'expected_name', 'question', 'answer'],
                ],
                'write' => true,
                'handler' => self::class . '::addQuestion',
            ],
            [
                'name' => 'faq_list_restore',
                'description' => 'Bring a deleted FAQ list back from the bin.',
                'inputSchema' => $idAndName,
                'write' => true,
                'handler' => self::class . '::restore',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $call
     * @return array<string, mixed>
     */
    public static function listLists(array $call): array
    {
        $lists = new FaqList($call['dataSource']);
        $showDeleted = ($call['arguments']['deleted'] ?? false) === true;
        $rows = $showDeleted ? $lists->listDeleted() : $lists->listAll();

        return ['total' => \count($rows), 'lists' => self::shapeList($rows)];
    }

    /**
     * @param array<int, array<string, mixed>> $rows from faq-list-list.sql or faq-list-list-deleted.sql
     * @return array<int, array<string, mixed>>
     */
    public static function shapeList(array $rows): array
    {
        $lists = [];
        foreach ($rows as $row) {
            $lists[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'slug' => (string) $row['slug'],
                'questions' => (int) $row['item_count'],
            ] + (isset($row['deleted_at']) ? ['deleted_at' => $row['deleted_at']] : ['updated_at' => $row['updated_at'] ?? null]);
        }

        return $lists;
    }

    /**
     * @param array<string, mixed> $call
     * @return array<string, mixed>
     */
    public static function questionsList(array $call): array
    {
        [$id, $expectedName] = self::idAndName($call['arguments'] ?? []);
        $list = (new FaqList($call['dataSource']))->getById($id);
        self::requireName($list, isDeleted: false, expectedName: $expectedName, id: $id);

        $rows = (new FaqItem($call['dataSource']))->forList($id);

        return ['list_id' => $id, 'list' => (string) $list['name'], 'total' => \count($rows), 'questions' => self::shapeQuestions($rows)];
    }

    /**
     * @param array<int, array<string, mixed>> $rows from faq-item-list.sql
     * @return array<int, array<string, mixed>>
     */
    public static function shapeQuestions(array $rows): array
    {
        $questions = [];
        foreach ($rows as $row) {
            $questions[] = [
                'id' => (int) $row['id'],
                'question' => (string) $row['question'],
                'answer' => (string) $row['answer'],
                'shown_on_site' => (bool) $row['is_published'],
            ];
        }

        return $questions;
    }

    /**
     * @param array<string, mixed> $call
     * @return array<string, mixed>
     */
    public static function delete(array $call): array
    {
        [$id, $expectedName] = self::idAndName($call['arguments'] ?? []);
        $lists = new FaqList($call['dataSource']);
        self::requireName($lists->getById($id), isDeleted: false, expectedName: $expectedName, id: $id);

        try {
            $list = self::bin($call, $lists)->delete($id);
        } catch (\Exception $e) {
            throw new McpToolException($e->getMessage());
        }

        return ['deleted' => true, 'id' => $id, 'name' => (string) $list['name'], 'undo' => "faq_list_restore with id {$id} and the same name"];
    }

    /**
     * @param array<string, mixed> $call
     * @return array<string, mixed>
     */
    public static function restore(array $call): array
    {
        [$id, $expectedName] = self::idAndName($call['arguments'] ?? []);
        $lists = new FaqList($call['dataSource']);
        self::requireName($lists->getById($id), isDeleted: true, expectedName: $expectedName, id: $id);

        try {
            $list = self::bin($call, $lists)->restore($id);
        } catch (\Exception $e) {
            throw new McpToolException($e->getMessage());
        }

        return ['restored' => true, 'id' => $id, 'name' => (string) $list['name']];
    }

    /**
     * @param array<string, mixed> $call
     * @return array<string, mixed>
     */
    public static function addQuestion(array $call): array
    {
        $arguments = $call['arguments'] ?? [];
        [$id, $expectedName] = self::idAndName($arguments);
        $lists = new FaqList($call['dataSource']);
        $list = $lists->getById($id);
        self::requireName($list, isDeleted: false, expectedName: $expectedName, id: $id);

        try {
            $pair = FaqText::cleanPair((string) ($arguments['question'] ?? ''), (string) ($arguments['answer'] ?? ''));
        } catch (\InvalidArgumentException $e) {
            throw new McpToolException($e->getMessage());
        }

        $questionId = (new FaqItem($call['dataSource']))->append($id, $pair['question'], $pair['answer'], isPublished: false);
        self::changelog($call)->log(
            description: 'Added a hidden question to FAQ list ' . $list['name'],
            recordType: self::RECORD_TYPE,
            recordId: $id,
        );

        return [
            'added' => true,
            'list_id' => $id,
            'list' => (string) $list['name'],
            'question_id' => $questionId,
            'shown_on_site' => false,
            'next_step' => 'Open the list in the admin (FAQs) and tick "Shown on the site" for this question to publish it, or leave it hidden.',
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{0: int, 1: string}
     */
    public static function idAndName(array $arguments): array
    {
        $id = $arguments['id'] ?? null;
        $name = $arguments['expected_name'] ?? null;
        $valid = \is_int($id) && $id > 0 && \is_string($name) && \trim($name) !== '';
        if (!$valid) throw new McpToolException('Give the list id (a whole number) and its exact expected_name.');

        return [$id, \trim($name)];
    }

    /**
     * Refuses unless the list exists, is in the state the tool needs (live to delete, in the bin to restore)
     * and its name is exactly the one given, so a slip of the id cannot change the wrong list.
     *
     * @param array<string, mixed>|null $list
     */
    public static function requireName(?array $list, bool $isDeleted, string $expectedName, int $id): void
    {
        $inRightState = $list !== null && (($list['deleted_at'] ?? null) !== null) === $isDeleted;
        if (!$inRightState) throw new McpToolException("No matching list with id {$id}. Check faq_lists_list.");
        if (\trim((string) $list['name']) !== $expectedName) {
            throw new McpToolException("The name does not match list {$id}. Check faq_lists_list and try again.");
        }
    }

    /** @param array<string, mixed> $call */
    private static function changelog(array $call): Changelog
    {
        $changelog = new Changelog($call['dataSource'], 'changelog');
        $changelog->setGetUser(fn(): array => ['username' => $call['actor']]);

        return $changelog;
    }

    /** @param array<string, mixed> $call */
    private static function bin(array $call, FaqList $lists): FaqBin
    {
        $changelog = self::changelog($call);

        $log = fn(string $action, int $id, string $name) => $changelog->log(
            description: $action . ': FAQ list ' . $name,
            recordType: self::RECORD_TYPE,
            recordId: $id,
        );

        return new FaqBin($lists, $log);
    }
}

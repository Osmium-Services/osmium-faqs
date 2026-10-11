<?php

declare(strict_types=1);

namespace Tests\Services\Faqs;

use Osmium\Core\Library\McpToolException;
use Osmium\Services\Faqs\Models\FaqItem;
use Osmium\Services\Faqs\Models\FaqList;
use Osmium\Services\Faqs\Models\FaqMcp;
use Tests\Integration\DatabaseTestCase;

require_once __DIR__ . '/../models/FaqText.php';
require_once __DIR__ . '/../models/FaqList.php';
require_once __DIR__ . '/../models/FaqItem.php';
require_once __DIR__ . '/../models/FaqBin.php';
require_once __DIR__ . '/../models/FaqMcp.php';

/**
 * The FAQs' MCP tools run for real against the tables: what each one changes, what it refuses (a wrong name, a
 * list in the wrong state, a question of another list) and that nothing it does shows a question on the site.
 */
class FaqMcpEndToEndTest extends DatabaseTestCase
{
    private FaqList $lists;
    private FaqItem $items;
    private string $runTag;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lists = new FaqList($this->db);
        $this->items = new FaqItem($this->db);
        $this->runTag = 'zq' . \substr(\uniqid(), -6);

        $prefix = $this->db->tablePrefix();
        $tableExists = $this->db->prepare("SHOW TABLES LIKE '{$prefix}faq_lists'")->single() !== false;
        if (!$tableExists) $this->markTestSkipped('The FAQs tables are not installed');
    }

    private function newList(string $suffix): array
    {
        $name = "{$this->runTag} {$suffix}";
        $id = $this->lists->create($name, \strtolower($this->runTag) . '-' . $suffix);

        return ['id' => $id, 'name' => $name];
    }

    private function call(array $arguments): array
    {
        return ['dataSource' => $this->db, 'arguments' => $arguments, 'actor' => 'tester'];
    }

    /** @return array<string, mixed> */
    private function item(int $id): array
    {
        $prefix = $this->db->tablePrefix();

        return $this->db->prepare("SELECT * FROM {$prefix}faq_items WHERE id = :id", [':id' => $id])->single();
    }

    private function itemCount(int $listId): int
    {
        return \count($this->items->forList($listId));
    }

    /** @return array<int, array<string, mixed>> */
    private function changelog(int $listId): array
    {
        $prefix = $this->db->tablePrefix();

        return $this->db->prepare(
            "SELECT * FROM {$prefix}changelog WHERE record_type = 'faq_list' AND record_id = :id ORDER BY id ASC",
            [':id' => $listId],
        )->resultset();
    }

    private function refused(callable $attempt, string $messagePart): void
    {
        try {
            $attempt();
            $this->fail("Expected a refusal containing: {$messagePart}");
        } catch (McpToolException $e) {
            $this->assertStringContainsString($messagePart, $e->getMessage());
        }
    }

    // ---- reading ------------------------------------------------------------------------------------------

    public function test_the_lists_tool_shows_live_lists_with_their_question_counts_and_the_bin_on_request(): void
    {
        $live = $this->newList('live');
        $gone = $this->newList('gone');
        $this->items->insert($live['id'], 'Q1', 'A1', 0, true);
        $this->items->insert($live['id'], 'Q2', 'A2', 1, false);
        $this->lists->softDelete($gone['id']);

        $normal = FaqMcp::listLists($this->call([]));
        $bin = FaqMcp::listLists($this->call(['deleted' => true]));
        $byName = fn(array $result): array => \array_column($result['lists'], null, 'name');

        $this->assertSame(2, $byName($normal)[$live['name']]['questions']);
        $this->assertArrayNotHasKey($gone['name'], $byName($normal));
        $this->assertArrayHasKey($gone['name'], $byName($bin));
        $this->assertArrayNotHasKey($live['name'], $byName($bin));
    }

    public function test_the_questions_tool_lists_them_in_order_with_shown_or_hidden(): void
    {
        $list = $this->newList('read');
        $this->items->insert($list['id'], 'Second', 'A2', 5, false);
        $this->items->insert($list['id'], 'First', 'A1', 1, true);

        $result = FaqMcp::questionsList($this->call(['id' => $list['id'], 'expected_name' => $list['name']]));

        $this->assertSame(['First', 'Second'], \array_column($result['questions'], 'question'));
        $this->assertSame([true, false], \array_column($result['questions'], 'shown_on_site'));
        $this->assertSame(2, $result['total']);
    }

    public function test_the_questions_tool_refuses_a_wrong_name_and_a_deleted_list(): void
    {
        $list = $this->newList('guard-read');
        $binned = $this->newList('binned-read');
        $this->lists->softDelete($binned['id']);

        $this->refused(fn() => FaqMcp::questionsList($this->call(['id' => $list['id'], 'expected_name' => 'Another name'])), 'does not match');
        $this->refused(fn() => FaqMcp::questionsList($this->call(['id' => $binned['id'], 'expected_name' => $binned['name']])), 'No matching list');
    }

    // ---- adding a question --------------------------------------------------------------------------------

    public function test_a_question_is_added_hidden_at_the_end_and_logged(): void
    {
        $list = $this->newList('add');
        $this->items->insert($list['id'], 'Existing', 'A', 3, true);

        $result = FaqMcp::addQuestion($this->call(['id' => $list['id'], 'expected_name' => $list['name'], 'question' => '  New question? ', 'answer' => ' New answer ']));

        $added = $this->item($result['question_id']);
        $this->assertSame(0, (int) $added['is_published']);
        $this->assertSame(4, (int) $added['sort_order']);
        $this->assertSame('New question?', $added['question']);
        $this->assertSame('New answer', $added['answer']);
        $this->assertFalse($result['shown_on_site']);
        $this->assertSame(2, $this->itemCount($list['id']));

        $entries = $this->changelog($list['id']);
        $this->assertCount(1, $entries);
        $this->assertStringContainsString($list['name'], $entries[0]['description']);
        $this->assertStringContainsString('tester', (string) $entries[0]['user_name']);
    }

    public function test_adding_to_an_empty_list_starts_the_order_at_the_beginning(): void
    {
        $list = $this->newList('empty');

        $result = FaqMcp::addQuestion($this->call(['id' => $list['id'], 'expected_name' => $list['name'], 'question' => 'Q', 'answer' => 'A']));

        $this->assertSame(0, (int) $this->item($result['question_id'])['sort_order']);
    }

    public function test_a_question_that_breaks_a_rule_is_refused_and_nothing_is_added(): void
    {
        $list = $this->newList('add-bad');
        $base = ['id' => $list['id'], 'expected_name' => $list['name']];

        $this->refused(fn() => FaqMcp::addQuestion($this->call($base + ['question' => '', 'answer' => 'A'])), 'needs a question');
        $this->refused(fn() => FaqMcp::addQuestion($this->call($base + ['question' => 'Q', 'answer' => ''])), 'needs an answer');
        $this->refused(fn() => FaqMcp::addQuestion($this->call($base + ['question' => \str_repeat('q', 501), 'answer' => 'A'])), '500 characters');
        $this->refused(fn() => FaqMcp::addQuestion($this->call($base + ['question' => 'Q', 'answer' => \str_repeat('a', 10001)])), '10,000 characters');

        $this->assertSame(0, $this->itemCount($list['id']));
        $this->assertSame([], $this->changelog($list['id']));
    }

    public function test_nothing_is_added_to_a_list_with_the_wrong_name_or_in_the_bin(): void
    {
        $list = $this->newList('add-guard');
        $binned = $this->newList('add-binned');
        $this->lists->softDelete($binned['id']);

        $this->refused(fn() => FaqMcp::addQuestion($this->call(['id' => $list['id'], 'expected_name' => 'Wrong', 'question' => 'Q', 'answer' => 'A'])), 'does not match');
        $this->refused(fn() => FaqMcp::addQuestion($this->call(['id' => $binned['id'], 'expected_name' => $binned['name'], 'question' => 'Q', 'answer' => 'A'])), 'No matching list');

        $this->assertSame(0, $this->itemCount($list['id']));
        $this->assertSame(0, $this->itemCount($binned['id']));
    }

    // ---- changing a question ------------------------------------------------------------------------------

    public function test_a_question_is_reworded_in_place_keeping_its_order_and_whether_it_is_shown(): void
    {
        $list = $this->newList('update');
        $shown = $this->items->insert($list['id'], 'Shown Q', 'Shown A', 7, true);
        $hidden = $this->items->insert($list['id'], 'Hidden Q', 'Hidden A', 9, false);
        $base = ['id' => $list['id'], 'expected_name' => $list['name']];

        $one = FaqMcp::updateQuestion($this->call($base + ['question_id' => $shown, 'expected_question' => 'Shown Q', 'question' => 'New shown Q', 'answer' => 'New shown A']));
        $two = FaqMcp::updateQuestion($this->call($base + ['question_id' => $hidden, 'expected_question' => 'Hidden Q', 'question' => 'New hidden Q', 'answer' => 'New hidden A']));

        $this->assertSame(['New shown Q', 7, 1], [$this->item($shown)['question'], (int) $this->item($shown)['sort_order'], (int) $this->item($shown)['is_published']]);
        $this->assertSame(['New hidden Q', 9, 0], [$this->item($hidden)['question'], (int) $this->item($hidden)['sort_order'], (int) $this->item($hidden)['is_published']]);
        $this->assertTrue($one['shown_on_site']);
        $this->assertFalse($two['shown_on_site']);
        $this->assertCount(2, $this->changelog($list['id']));
    }

    public function test_a_wrong_current_text_an_unknown_id_or_a_question_of_another_list_is_refused_and_changes_nothing(): void
    {
        $list = $this->newList('update-guard');
        $other = $this->newList('update-other');
        $mine = $this->items->insert($list['id'], 'Mine', 'Mine A', 0, true);
        $theirs = $this->items->insert($other['id'], 'Theirs', 'Theirs A', 0, true);
        $base = ['id' => $list['id'], 'expected_name' => $list['name'], 'question' => 'Changed', 'answer' => 'Changed'];

        $this->refused(fn() => FaqMcp::updateQuestion($this->call($base + ['question_id' => $mine, 'expected_question' => 'Not the text'])), 'does not match');
        $this->refused(fn() => FaqMcp::updateQuestion($this->call($base + ['question_id' => 987654321, 'expected_question' => 'Mine'])), 'No question');
        $this->refused(fn() => FaqMcp::updateQuestion($this->call($base + ['question_id' => $theirs, 'expected_question' => 'Theirs'])), 'No question');

        $this->assertSame('Mine', $this->item($mine)['question']);
        $this->assertSame('Theirs', $this->item($theirs)['question']);
        $this->assertSame([], $this->changelog($list['id']));
    }

    public function test_update_needs_a_whole_number_question_id_and_the_current_text(): void
    {
        $list = $this->newList('update-args');
        $question = $this->items->insert($list['id'], 'Q', 'A', 0, true);
        $base = ['id' => $list['id'], 'expected_name' => $list['name'], 'question' => 'New', 'answer' => 'New'];

        foreach ([['question_id' => (string) $question, 'expected_question' => 'Q'], ['question_id' => 0, 'expected_question' => 'Q'], ['question_id' => $question, 'expected_question' => ' '], ['expected_question' => 'Q']] as $extra) {
            $this->refused(fn() => FaqMcp::updateQuestion($this->call($base + $extra)), 'question_id');
        }
        $this->assertSame('Q', $this->item($question)['question']);
    }

    public function test_new_wording_that_breaks_a_rule_is_refused_and_the_old_wording_stays(): void
    {
        $list = $this->newList('update-bad');
        $question = $this->items->insert($list['id'], 'Q', 'A', 0, true);
        $base = ['id' => $list['id'], 'expected_name' => $list['name'], 'question_id' => $question, 'expected_question' => 'Q'];

        $this->refused(fn() => FaqMcp::updateQuestion($this->call($base + ['question' => 'New', 'answer' => ''])), 'needs an answer');
        $this->refused(fn() => FaqMcp::updateQuestion($this->call($base + ['question' => \str_repeat('q', 501), 'answer' => 'A'])), '500 characters');

        $this->assertSame(['Q', 'A'], [$this->item($question)['question'], $this->item($question)['answer']]);
    }

    // ---- the bin ------------------------------------------------------------------------------------------

    public function test_delete_moves_the_list_to_the_bin_keeping_its_questions_and_is_logged(): void
    {
        $list = $this->newList('delete');
        $this->items->insert($list['id'], 'Q', 'A', 0, true);

        $result = FaqMcp::delete($this->call(['id' => $list['id'], 'expected_name' => $list['name']]));

        $this->assertTrue($result['deleted']);
        $this->assertNotNull($this->lists->getById($list['id'])['deleted_at']);
        $this->assertSame(1, $this->itemCount($list['id']));
        $entries = $this->changelog($list['id']);
        $this->assertCount(1, $entries);
        $this->assertStringStartsWith('Deleted: FAQ list', $entries[0]['description']);
    }

    public function test_restore_brings_the_list_back_with_its_questions_still_shown_or_hidden_as_before(): void
    {
        $list = $this->newList('restore');
        $shown = $this->items->insert($list['id'], 'Shown', 'A', 0, true);
        $hidden = $this->items->insert($list['id'], 'Hidden', 'A', 1, false);
        $this->lists->softDelete($list['id']);

        $result = FaqMcp::restore($this->call(['id' => $list['id'], 'expected_name' => $list['name']]));

        $this->assertTrue($result['restored']);
        $this->assertNull($this->lists->getById($list['id'])['deleted_at']);
        $this->assertSame(1, (int) $this->item($shown)['is_published']);
        $this->assertSame(0, (int) $this->item($hidden)['is_published']);
    }

    public function test_delete_and_restore_refuse_a_wrong_name_and_a_list_in_the_wrong_state(): void
    {
        $live = $this->newList('state-live');
        $binned = $this->newList('state-binned');
        $this->lists->softDelete($binned['id']);

        $this->refused(fn() => FaqMcp::delete($this->call(['id' => $live['id'], 'expected_name' => 'Wrong'])), 'does not match');
        $this->refused(fn() => FaqMcp::delete($this->call(['id' => $binned['id'], 'expected_name' => $binned['name']])), 'No matching list');
        $this->refused(fn() => FaqMcp::restore($this->call(['id' => $live['id'], 'expected_name' => $live['name']])), 'No matching list');
        $this->refused(fn() => FaqMcp::restore($this->call(['id' => $binned['id'], 'expected_name' => 'Wrong'])), 'does not match');

        $this->assertNull($this->lists->getById($live['id'])['deleted_at']);
        $this->assertNotNull($this->lists->getById($binned['id'])['deleted_at']);
    }

    public function test_no_tool_ever_shows_a_hidden_question(): void
    {
        $list = $this->newList('never-publish');
        $base = ['id' => $list['id'], 'expected_name' => $list['name']];

        $added = FaqMcp::addQuestion($this->call($base + ['question' => 'Q', 'answer' => 'A']));
        FaqMcp::updateQuestion($this->call($base + ['question_id' => $added['question_id'], 'expected_question' => 'Q', 'question' => 'Q2', 'answer' => 'A2']));
        FaqMcp::delete($this->call($base));
        FaqMcp::restore($this->call($base));

        $this->assertSame(0, (int) $this->item($added['question_id'])['is_published']);
        $this->assertSame([], $this->items->publishedForList($list['id']));
    }
}

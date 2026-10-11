<?php

declare(strict_types=1);

namespace Tests\Services\Faqs;

use Osmium\Core\Library\McpToolException;
use Osmium\Services\Faqs\Models\FaqMcp;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/FaqMcp.php';

/**
 * The FAQs' MCP tools without the database: which are writes and which are destructive, how replies are shaped,
 * and the id-and-name guard that stops a slip of the id changing the wrong list.
 */
final class FaqMcpTest extends TestCase
{
    /** @return array<string, array<string, mixed>> */
    private function tools(): array
    {
        $tools = [];
        foreach (FaqMcp::tools([]) as $tool) {
            $tools[$tool['name']] = $tool;
        }

        return $tools;
    }

    public function test_it_offers_exactly_the_six_expected_tools(): void
    {
        $this->assertSame(
            ['faq_lists_list', 'faq_questions_list', 'faq_list_delete', 'faq_question_add', 'faq_question_update', 'faq_list_restore'],
            \array_keys($this->tools()),
        );
    }

    public function test_only_the_two_lists_are_read_tools(): void
    {
        foreach ($this->tools() as $name => $tool) {
            $this->assertSame(!\in_array($name, ['faq_lists_list', 'faq_questions_list'], true), $tool['write'] ?? false, $name);
        }
    }

    public function test_delete_and_update_are_destructive_and_adding_or_restoring_is_not(): void
    {
        foreach ($this->tools() as $name => $tool) {
            $this->assertSame(\in_array($name, ['faq_list_delete', 'faq_question_update'], true), $tool['destructive'] ?? false, $name);
        }
    }

    public function test_there_is_no_publish_reorder_remove_or_permanent_delete_tool(): void
    {
        foreach (\array_keys($this->tools()) as $name) {
            $this->assertDoesNotMatchRegularExpression('/publish|reorder|remove|permanent|purge|create/', $name);
        }
    }

    public function test_every_tool_that_changes_a_list_needs_its_id_and_exact_name(): void
    {
        foreach (['faq_list_delete', 'faq_list_restore', 'faq_question_add', 'faq_question_update', 'faq_questions_list'] as $name) {
            $this->assertContains('id', $this->tools()[$name]['inputSchema']['required'], $name);
            $this->assertContains('expected_name', $this->tools()[$name]['inputSchema']['required'], $name);
        }
        $this->assertContains('expected_question', $this->tools()['faq_question_update']['inputSchema']['required']);
    }

    public function test_the_list_of_lists_shows_the_bin_date_only_for_deleted_ones(): void
    {
        $live = FaqMcp::shapeList([['id' => '1', 'name' => 'Delivery', 'slug' => 'delivery', 'item_count' => '4', 'updated_at' => '2026-10-01 10:00:00']]);
        $bin = FaqMcp::shapeList([['id' => '2', 'name' => 'Old', 'slug' => 'old', 'item_count' => '0', 'deleted_at' => '2026-10-02 10:00:00', 'updated_at' => '2026-09-01 10:00:00']]);

        $this->assertSame([['id' => 1, 'name' => 'Delivery', 'slug' => 'delivery', 'questions' => 4, 'updated_at' => '2026-10-01 10:00:00']], $live);
        $this->assertSame([['id' => 2, 'name' => 'Old', 'slug' => 'old', 'questions' => 0, 'deleted_at' => '2026-10-02 10:00:00']], $bin);
    }

    public function test_questions_show_whether_each_is_shown_or_hidden(): void
    {
        $questions = FaqMcp::shapeQuestions([
            ['id' => '1', 'question' => 'Q1', 'answer' => 'A1', 'is_published' => '1', 'sort_order' => '1'],
            ['id' => '2', 'question' => 'Q2', 'answer' => 'A2', 'is_published' => '0', 'sort_order' => '2'],
        ]);

        $this->assertSame([true, false], \array_column($questions, 'shown_on_site'));
        $this->assertSame(['id', 'question', 'answer', 'shown_on_site'], \array_keys($questions[0]));
    }

    public function test_the_id_and_name_must_be_a_positive_whole_number_and_a_non_blank_string(): void
    {
        $this->assertSame([5, 'Delivery'], FaqMcp::idAndName(['id' => 5, 'expected_name' => ' Delivery ']));
        foreach ([[], ['id' => 5], ['expected_name' => 'x'], ['id' => '5', 'expected_name' => 'x'], ['id' => 0, 'expected_name' => 'x'], ['id' => -2, 'expected_name' => 'x'], ['id' => 5, 'expected_name' => '  '], ['id' => 5.5, 'expected_name' => 'x']] as $arguments) {
            try {
                FaqMcp::idAndName($arguments);
                $this->fail('Accepted ' . \json_encode($arguments));
            } catch (McpToolException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_missing_list_or_one_in_the_wrong_state_or_with_another_name_is_refused(): void
    {
        $live = ['name' => 'Delivery', 'deleted_at' => null];
        $binned = ['name' => 'Delivery', 'deleted_at' => '2026-10-01 10:00:00'];

        FaqMcp::requireName($live, false, 'Delivery', 1);
        FaqMcp::requireName($binned, true, 'Delivery', 1);
        $this->addToAssertionCount(2);

        foreach ([[null, false, 'Delivery'], [$live, true, 'Delivery'], [$binned, false, 'Delivery'], [$live, false, 'Returns']] as [$list, $isDeleted, $name]) {
            try {
                FaqMcp::requireName($list, $isDeleted, $name, 1);
                $this->fail('A bad list request was accepted');
            } catch (McpToolException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace Tests\Services\Faqs;

use Osmium\Services\Faqs\Models\FaqHooks;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/FaqHooks.php';

/**
 * The faq.items hook answers only when a list is asked for, so a product with none chosen costs no query.
 */
final class FaqHooksTest extends TestCase
{
    public function test_items_gives_no_answer_for_a_blank_slug(): void
    {
        $this->assertNull(FaqHooks::items(['slug' => '', 'dataSource' => null]));
        $this->assertNull(FaqHooks::items(['slug' => '  ', 'dataSource' => null]));
        $this->assertNull(FaqHooks::items(['dataSource' => null]));
    }
}

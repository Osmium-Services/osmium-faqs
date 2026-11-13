<?php

declare(strict_types=1);

namespace Tests\Services\Faqs;

use Osmium\Services\Faqs\Models\FaqAdminBar;
use Osmium\Services\Faqs\Models\Faqs;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/Faqs.php';
require_once __DIR__ . '/../models/FaqAdminBar.php';

/**
 * The staff bar's FAQ answers: a page that printed a list announces it by id and gets an "Edit FAQs" link.
 */
final class FaqAdminBarTest extends TestCase
{
    protected function setUp(): void
    {
        Faqs::forgetShown();
    }

    public function test_a_page_that_printed_no_list_announces_nothing(): void
    {
        $this->assertNull(FaqAdminBar::context(['data' => []]));
    }

    public function test_a_page_that_printed_a_list_announces_it_by_id(): void
    {
        $this->setShown(7);

        $this->assertSame('faq-list:7', FaqAdminBar::context(['data' => []]));
    }

    public function test_the_link_opens_that_lists_editor_with_the_faq_icon(): void
    {
        $links = FaqAdminBar::links(['context' => 'faq-list:7']);

        $this->assertSame('faqs/edit/?id=7', $links[0]['path']);
        $this->assertSame('Edit FAQs', $links[0]['label']);
        $this->assertSame('bx-help-circle', $links[0]['icon']);
        $this->assertTrue($links[0]['primary']);
    }

    public function test_other_or_malformed_contexts_get_no_links(): void
    {
        $this->assertNull(FaqAdminBar::links(['context' => 'product:5']));
        $this->assertNull(FaqAdminBar::links(['context' => 'faq-list:abc']));
        $this->assertNull(FaqAdminBar::links([]));
    }

    private function setShown(int $id): void
    {
        $property = new \ReflectionProperty(Faqs::class, 'firstShownListId');
        $property->setValue(null, $id);
    }
}

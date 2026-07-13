<?php

namespace Tests\Unit;

use App\Enums\EmailSummarySection;
use PHPUnit\Framework\TestCase;

class EmailSummarySectionTest extends TestCase
{
    public function test_values_returns_all_section_strings(): void
    {
        $this->assertEquals(
            ['budgeting', 'investing', 'net_worth', 'upcoming_scheduled', 'category_changes', 'warnings'],
            EmailSummarySection::values()
        );
    }

    public function test_each_case_has_a_label_and_description(): void
    {
        foreach (EmailSummarySection::cases() as $section) {
            $this->assertNotEmpty($section->label(), "{$section->value} has no label");
            $this->assertNotEmpty($section->description(), "{$section->value} has no description");
        }
    }
}

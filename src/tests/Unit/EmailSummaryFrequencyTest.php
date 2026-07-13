<?php

namespace Tests\Unit;

use App\Enums\EmailSummaryFrequency;
use PHPUnit\Framework\TestCase;

class EmailSummaryFrequencyTest extends TestCase
{
    public function test_values_returns_all_frequency_strings(): void
    {
        $this->assertEquals(['daily', 'weekly', 'monthly'], EmailSummaryFrequency::values());
    }

    public function test_each_case_has_a_label(): void
    {
        foreach (EmailSummaryFrequency::cases() as $frequency) {
            $this->assertNotEmpty($frequency->label(), "{$frequency->value} has no label");
        }
    }
}

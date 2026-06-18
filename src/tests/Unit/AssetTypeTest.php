<?php

namespace Tests\Unit;

use App\Enums\AssetType;
use PHPUnit\Framework\TestCase;

class AssetTypeTest extends TestCase
{
    public function test_values_returns_all_type_strings(): void
    {
        $this->assertEquals(['stock', 'crypto', 'real_estate', 'bond'], AssetType::values());
    }

    public function test_each_case_has_a_label(): void
    {
        foreach (AssetType::cases() as $type) {
            $this->assertNotEmpty($type->label(), "{$type->value} has no label");
        }
    }

    public function test_labels_are_human_readable(): void
    {
        $this->assertSame('Stock', AssetType::Stock->label());
        $this->assertSame('Crypto', AssetType::Crypto->label());
        $this->assertSame('Real Estate', AssetType::RealEstate->label());
        $this->assertSame('Bond', AssetType::Bond->label());
    }
}

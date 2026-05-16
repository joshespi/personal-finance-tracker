<?php

namespace Tests\Unit;

use App\Enums\PriceSource;
use PHPUnit\Framework\TestCase;

class PriceSourceTest extends TestCase
{
    public function test_each_case_has_a_label(): void
    {
        foreach (PriceSource::cases() as $source) {
            $this->assertNotEmpty($source->label(), "{$source->value} has no label");
        }
    }

    public function test_labels_are_human_readable(): void
    {
        $this->assertSame('Finnhub',   PriceSource::Finnhub->label());
        $this->assertSame('CoinGecko', PriceSource::CoinGecko->label());
    }

    public function test_values_are_lowercase_strings(): void
    {
        $this->assertSame('finnhub',   PriceSource::Finnhub->value);
        $this->assertSame('coingecko', PriceSource::CoinGecko->value);
    }
}

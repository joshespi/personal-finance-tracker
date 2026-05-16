<?php

namespace Tests\Unit;

use App\Enums\PriceSource;
use App\Models\Asset;
use Tests\TestCase;

class AssetModelTest extends TestCase
{
    public function test_effective_price_source_defaults_coingecko_for_crypto(): void
    {
        $asset = Asset::factory()->crypto()->make(['price_source' => null]);

        $this->assertSame(PriceSource::CoinGecko->value, $asset->effectivePriceSource());
    }

    public function test_effective_price_source_defaults_finnhub_for_stock(): void
    {
        $asset = Asset::factory()->stock()->make(['price_source' => null]);

        $this->assertSame(PriceSource::Finnhub->value, $asset->effectivePriceSource());
    }

    public function test_effective_price_source_defaults_finnhub_for_bond(): void
    {
        $asset = Asset::factory()->bond()->make(['price_source' => null]);

        $this->assertSame(PriceSource::Finnhub->value, $asset->effectivePriceSource());
    }

    public function test_explicit_price_source_overrides_crypto_default(): void
    {
        // ARKB is crypto-classified but priced via Finnhub
        $asset = Asset::factory()->crypto()->make(['price_source' => PriceSource::Finnhub->value]);

        $this->assertSame(PriceSource::Finnhub->value, $asset->effectivePriceSource());
    }

    public function test_explicit_price_source_overrides_stock_default(): void
    {
        $asset = Asset::factory()->stock()->make(['price_source' => PriceSource::CoinGecko->value]);

        $this->assertSame(PriceSource::CoinGecko->value, $asset->effectivePriceSource());
    }
}

<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class FetchAssetPricesTest extends TestCase
{
    public function test_coingecko_connection_failure_does_not_prevent_stock_fetch(): void
    {
        $portfolio = Portfolio::factory()->create();
        $crypto    = Asset::factory()->crypto()->create(['symbol' => 'BTC']);
        $stock     = Asset::factory()->stock()->create(['symbol' => 'AAPL']);

        Transaction::factory()->for($portfolio)->for($crypto)->buy()->create(['transacted_at' => now()]);
        Transaction::factory()->for($portfolio)->for($stock)->buy()->create(['transacted_at' => now()]);

        config(['services.finnhub.key' => 'test-key']);

        // A CoinGecko connection failure used to throw uncaught out of fetchCryptoPrices(),
        // aborting handle() before fetchStockPrices() ever ran — regression guard.
        Http::fake([
            'api.coingecko.com/*' => Http::failedConnection(),
            'finnhub.io/*'        => Http::response(['c' => 150.0]),
        ]);
        Log::spy();

        $this->artisan('assets:fetch-prices')->assertExitCode(0);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'finnhub.io'));
        $this->assertDatabaseHas('asset_prices', ['asset_id' => $stock->id, 'price' => 150.0]);
        $this->assertDatabaseMissing('asset_prices', ['asset_id' => $crypto->id]);
        Log::shouldHaveReceived('error')->withArgs(fn ($msg) => str_contains($msg, 'CoinGecko'))->once();
    }

    public function test_finnhub_connection_failure_on_one_asset_does_not_abort_remaining_assets(): void
    {
        $portfolio = Portfolio::factory()->create();
        $bad       = Asset::factory()->stock()->create(['symbol' => 'BAD']);
        $good      = Asset::factory()->stock()->create(['symbol' => 'GOOD']);

        Transaction::factory()->for($portfolio)->for($bad)->buy()->create(['transacted_at' => now()]);
        Transaction::factory()->for($portfolio)->for($good)->buy()->create(['transacted_at' => now()]);

        config(['services.finnhub.key' => 'test-key']);

        Http::fake(fn ($request) => $request['symbol'] === 'BAD'
            ? Http::failedConnection()
            : Http::response(['c' => 200.0]));

        $this->artisan('assets:fetch-prices')->assertExitCode(0);

        $this->assertDatabaseMissing('asset_prices', ['asset_id' => $bad->id]);
        $this->assertDatabaseHas('asset_prices', ['asset_id' => $good->id, 'price' => 200.0]);
    }

    public function test_no_finnhub_key_skips_stocks_without_error(): void
    {
        $portfolio = Portfolio::factory()->create();
        $stock     = Asset::factory()->stock()->create(['symbol' => 'AAPL']);
        Transaction::factory()->for($portfolio)->for($stock)->buy()->create(['transacted_at' => now()]);

        config(['services.finnhub.key' => null]);
        Http::fake();

        $this->artisan('assets:fetch-prices')->assertExitCode(0);

        $this->assertDatabaseMissing('asset_prices', ['asset_id' => $stock->id]);
    }
}

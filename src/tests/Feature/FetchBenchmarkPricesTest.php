<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchBenchmarkPricesTest extends TestCase
{
    public function test_stock_benchmark_connection_failure_does_not_prevent_crypto_benchmark_fetch(): void
    {
        config(['services.finnhub.key' => 'test-key']);

        // A Finnhub (SPY) connection failure used to throw uncaught out of
        // fetchStockCandles(), aborting handle() before fetchCryptoCandles() (BTC) ran.
        Http::fake([
            'finnhub.io/*'        => Http::failedConnection(),
            'api.coingecko.com/*' => Http::response([
                'prices' => [[now()->subDay()->valueOf(), 60000]],
            ]),
        ]);

        $this->artisan('benchmarks:fetch', ['--from' => now()->subDays(2)->toDateString(), '--to' => now()->toDateString()])
            ->assertExitCode(0);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'coingecko'));
        $this->assertDatabaseHas('benchmark_prices', ['ticker' => 'BTC']);
        $this->assertDatabaseMissing('benchmark_prices', ['ticker' => 'SPY']);
    }

    public function test_crypto_benchmark_connection_failure_does_not_crash_command(): void
    {
        config(['services.finnhub.key' => 'test-key']);

        Http::fake([
            'finnhub.io/*'        => Http::response(['s' => 'ok', 't' => [now()->subDay()->timestamp], 'c' => [500]]),
            'api.coingecko.com/*' => Http::failedConnection(),
        ]);

        $this->artisan('benchmarks:fetch', ['--from' => now()->subDays(2)->toDateString(), '--to' => now()->toDateString()])
            ->assertExitCode(0);

        $this->assertDatabaseHas('benchmark_prices', ['ticker' => 'SPY']);
        $this->assertDatabaseMissing('benchmark_prices', ['ticker' => 'BTC']);
    }
}

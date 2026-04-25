<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TickerSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticker_search_requires_auth(): void
    {
        $this->get(route('tickers.search', ['q' => 'AAPL']))->assertRedirect(route('login'));
    }

    public function test_returns_empty_for_blank_query(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('tickers.search', ['q' => '']))
            ->assertOk()
            ->assertJson([]);
    }

    public function test_returns_local_assets_first(): void
    {
        $user = User::factory()->create();
        Asset::create(['symbol' => 'AAPL', 'name' => 'Apple Inc', 'asset_type' => 'stock']);
        Asset::create(['symbol' => 'AMZN', 'name' => 'Amazon',    'asset_type' => 'stock']);

        Http::fake(); // prevent external calls

        $response = $this->actingAs($user)->get(route('tickers.search', ['q' => 'A', 'type' => 'stock']));

        $response->assertOk();
        $data = $response->json();
        $symbols = collect($data)->pluck('symbol')->all();

        $this->assertContains('AAPL', $symbols);
    }

    public function test_local_results_have_required_fields(): void
    {
        $user = User::factory()->create();
        Asset::create(['symbol' => 'MSFT', 'name' => 'Microsoft', 'asset_type' => 'stock']);

        Http::fake();

        $response = $this->actingAs($user)->get(route('tickers.search', ['q' => 'MSFT', 'type' => 'stock']));
        $response->assertOk();

        $data = $response->json();
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('symbol', $data[0]);
        $this->assertArrayHasKey('name',   $data[0]);
        $this->assertArrayHasKey('type',   $data[0]);
    }

    public function test_crypto_search_filters_by_type(): void
    {
        $user = User::factory()->create();
        Asset::create(['symbol' => 'BTC', 'name' => 'Bitcoin', 'asset_type' => 'crypto']);
        Asset::create(['symbol' => 'BTG', 'name' => 'BT Group', 'asset_type' => 'stock']);

        Http::fake();

        $response = $this->actingAs($user)->get(route('tickers.search', ['q' => 'BT', 'type' => 'crypto']));
        $response->assertOk();

        $data    = $response->json();
        $symbols = collect($data)->pluck('symbol')->all();
        $this->assertContains('BTC', $symbols);
        $this->assertNotContains('BTG', $symbols);
    }
}

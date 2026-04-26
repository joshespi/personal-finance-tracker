<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TickerSearchTest extends TestCase
{
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
        Asset::factory()->stock()->create(['symbol' => 'AAPL', 'name' => 'Apple Inc']);
        Asset::factory()->stock()->create(['symbol' => 'AMZN', 'name' => 'Amazon']);

        Http::fake();

        $response = $this->actingAs($user)->get(route('tickers.search', ['q' => 'A', 'type' => 'stock']));

        $response->assertOk();
        $symbols = collect($response->json())->pluck('symbol')->all();

        $this->assertContains('AAPL', $symbols);
    }

    public function test_local_results_have_required_fields(): void
    {
        $user = User::factory()->create();
        Asset::factory()->stock()->create(['symbol' => 'MSFT', 'name' => 'Microsoft']);

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
        Asset::factory()->crypto()->create(['symbol' => 'BTC', 'name' => 'Bitcoin']);
        Asset::factory()->stock()->create(['symbol' => 'BTG', 'name' => 'BT Group']);

        Http::fake();

        $response = $this->actingAs($user)->get(route('tickers.search', ['q' => 'BT', 'type' => 'crypto']));
        $response->assertOk();

        $symbols = collect($response->json())->pluck('symbol')->all();
        $this->assertContains('BTC', $symbols);
        $this->assertNotContains('BTG', $symbols);
    }
}

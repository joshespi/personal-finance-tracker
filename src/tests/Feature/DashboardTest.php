<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Liability;
use App\Models\LiabilityBalance;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    private function makePricedAsset(string $symbol, float $price): Asset
    {
        $asset = Asset::factory()->crypto()->create(['symbol' => $symbol, 'name' => $symbol]);
        AssetPrice::factory()->for($asset)->create(['price' => $price]);

        return $asset;
    }

    public function test_dashboard_requires_auth(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_dashboard_shows_welcome_when_user_has_no_data(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Welcome to your financial tracker')
            ->assertSee('Create a portfolio')
            ->assertSee('Add a spending account');
    }

    public function test_dashboard_shows_net_worth_when_user_has_only_cash(): void
    {
        $account = CashAccount::factory()->create();
        CashTransaction::factory()->for($account, 'cashAccount')->deposit()->create(['amount' => 1000]);

        $this->actingAs($account->user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Welcome to your financial tracker')
            ->assertSee('Net Worth')
            ->assertSee('$1,000.00');
    }

    public function test_dashboard_shows_portfolio_names(): void
    {
        $user = User::factory()->create();
        Portfolio::factory()->for($user)->create(['name' => 'My Exchange']);
        Portfolio::factory()->for($user)->create(['name' => 'Cold Wallet']);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('My Exchange')
            ->assertSee('Cold Wallet');
    }

    public function test_dashboard_aggregates_holdings_across_portfolios(): void
    {
        $user     = User::factory()->create();
        $exchange = Portfolio::factory()->for($user)->create();
        $wallet   = Portfolio::factory()->for($user)->create();
        $btc      = $this->makePricedAsset('BTC', 50000.0);

        Transaction::factory()->for($exchange)->for($btc)->buy()->create(['quantity' => 0.3, 'price_per_unit' => 40000]);
        Transaction::factory()->for($wallet)->for($btc)->buy()->create(['quantity' => 0.5, 'price_per_unit' => 42000]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertSee('All Holdings')->assertSee('BTC');

        $allHoldings = $response->viewData('allHoldings');
        $this->assertCount(1, $allHoldings);

        $btcHolding = $allHoldings->first();
        $this->assertEquals(0.8, $btcHolding['quantity']);
        $this->assertEquals(0.3 * 40000 + 0.5 * 42000, $btcHolding['total_cost']);
        $this->assertEquals(round(0.8 * 50000, 2), $btcHolding['current_value']);
    }

    public function test_dashboard_aggregates_multiple_assets(): void
    {
        $user = User::factory()->create();
        $p1   = Portfolio::factory()->for($user)->create();
        $p2   = Portfolio::factory()->for($user)->create();
        $btc  = $this->makePricedAsset('BTC', 50000.0);
        $eth  = $this->makePricedAsset('ETH', 3000.0);

        Transaction::factory()->for($p1)->for($btc)->buy()->create(['quantity' => 1.0, 'price_per_unit' => 40000]);
        Transaction::factory()->for($p2)->for($eth)->buy()->create(['quantity' => 2.0, 'price_per_unit' => 2000]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $allHoldings = $response->viewData('allHoldings');
        $this->assertCount(2, $allHoldings);

        $symbols = $allHoldings->pluck('asset')->map(fn ($a) => $a->symbol)->all();
        $this->assertContains('BTC', $symbols);
        $this->assertContains('ETH', $symbols);
    }

    public function test_dashboard_all_holdings_empty_when_no_transactions(): void
    {
        $user = User::factory()->create();
        Portfolio::factory()->for($user)->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $allHoldings = $response->viewData('allHoldings');
        $this->assertCount(0, $allHoldings);
        $response->assertDontSee('All Holdings');
    }

    public function test_dashboard_only_shows_current_users_portfolios(): void
    {
        $user = User::factory()->create();

        Portfolio::factory()->for($user)->create(['name' => 'Mine']);
        Portfolio::factory()->create(['name' => 'Theirs']);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertSee('Mine')
            ->assertDontSee('Theirs');
    }

    public function test_all_holdings_pct_sums_to_100_and_is_proportional(): void
    {
        $portfolio = Portfolio::factory()->create();
        $btc       = $this->makePricedAsset('BTC', 50000.0);
        $eth       = $this->makePricedAsset('ETH', 3000.0);

        Transaction::factory()->for($portfolio)->for($btc)->buy()->create(['quantity' => 1.0, 'price_per_unit' => 40000]);
        Transaction::factory()->for($portfolio)->for($eth)->buy()->create(['quantity' => 2.0, 'price_per_unit' => 2000]);

        $response    = $this->actingAs($portfolio->user)->get(route('dashboard'));
        $allHoldings = $response->viewData('allHoldings');

        $btcRow = $allHoldings->first(fn ($h) => $h['asset']->symbol === 'BTC');
        $ethRow = $allHoldings->first(fn ($h) => $h['asset']->symbol === 'ETH');

        $this->assertEquals(round(50000 / 56000 * 100, 2), $btcRow['pct']);
        $this->assertEquals(round(6000  / 56000 * 100, 2), $ethRow['pct']);
        $this->assertEqualsWithDelta(100.0, $btcRow['pct'] + $ethRow['pct'], 0.1);
    }

    public function test_interest_bleed_banner_shows_when_revolving_debt_exists(): void
    {
        $user      = User::factory()->create();
        $liability = Liability::factory()->for($user)->create(['liability_type' => 'credit_card', 'interest_rate' => 24.0]);
        LiabilityBalance::factory()->for($liability)->create(['balance' => 5000]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Interest bleed')
            ->assertSee('View payoff plan');
    }

    public function test_interest_bleed_banner_hidden_when_no_revolving_debt(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Interest bleed');
    }

    public function test_interest_bleed_banner_hidden_when_only_mortgage(): void
    {
        $user      = User::factory()->create();
        $liability = Liability::factory()->for($user)->create(['liability_type' => 'mortgage', 'interest_rate' => 6.5]);
        LiabilityBalance::factory()->for($liability)->create(['balance' => 300000]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Interest bleed');
    }

    public function test_dashboard_sorted_by_value_descending(): void
    {
        $portfolio = Portfolio::factory()->create();
        $btc       = $this->makePricedAsset('BTC',  50000.0);
        $eth       = $this->makePricedAsset('ETH',  3000.0);
        $doge      = $this->makePricedAsset('DOGE', 0.1);

        Transaction::factory()->for($portfolio)->for($doge)->buy()->create(['quantity' => 100, 'price_per_unit' => 0.08]);
        Transaction::factory()->for($portfolio)->for($btc)->buy()->create(['quantity' => 1.0, 'price_per_unit' => 40000]);
        Transaction::factory()->for($portfolio)->for($eth)->buy()->create(['quantity' => 2.0, 'price_per_unit' => 2000]);

        $response    = $this->actingAs($portfolio->user)->get(route('dashboard'));
        $allHoldings = $response->viewData('allHoldings');
        $symbols     = $allHoldings->pluck('asset')->map(fn ($a) => $a->symbol)->values()->all();

        $this->assertEquals(['BTC', 'ETH', 'DOGE'], $symbols);
    }
}

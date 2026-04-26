<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makePortfolio(User $user, string $name = 'Portfolio'): Portfolio
    {
        return Portfolio::create(['user_id' => $user->id, 'name' => $name, 'currency' => 'USD']);
    }

    private function makeAsset(string $symbol, ?float $price = null): Asset
    {
        $asset = Asset::create(['symbol' => $symbol, 'name' => $symbol, 'asset_type' => 'crypto']);
        if ($price !== null) {
            AssetPrice::create([
                'asset_id'    => $asset->id,
                'price'       => $price,
                'currency'    => 'USD',
                'recorded_at' => now(),
            ]);
        }
        return $asset;
    }

    private function addBuy(Portfolio $portfolio, Asset $asset, float $qty, float $price, float $fees = 0): void
    {
        Transaction::create([
            'portfolio_id'   => $portfolio->id,
            'asset_id'       => $asset->id,
            'type'           => 'buy',
            'quantity'       => $qty,
            'price_per_unit' => $price,
            'fees'           => $fees,
            'currency'       => 'USD',
            'transacted_at'  => '2024-01-01',
        ]);
    }

    public function test_dashboard_requires_auth(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_dashboard_shows_welcome_when_user_has_no_data(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Welcome to your financial tracker');
        $response->assertSee('Create a portfolio');
        $response->assertSee('Add a cash account');
    }

    public function test_dashboard_shows_net_worth_when_user_has_only_cash(): void
    {
        $user = $this->makeUser();
        $account = \App\Models\CashAccount::create([
            'user_id'      => $user->id,
            'name'         => 'Checking',
            'account_type' => 'checking',
            'currency'     => 'USD',
        ]);
        $account->transactions()->create(['type' => 'deposit', 'amount' => 1000, 'occurred_at' => '2026-04-26']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Welcome to your financial tracker');
        $response->assertSee('Net Worth');
        $response->assertSee('$1,000.00');
    }

    public function test_dashboard_shows_portfolio_names(): void
    {
        $user = $this->makeUser();
        $this->makePortfolio($user, 'My Exchange');
        $this->makePortfolio($user, 'Cold Wallet');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('My Exchange');
        $response->assertSee('Cold Wallet');
    }

    public function test_dashboard_aggregates_holdings_across_portfolios(): void
    {
        $user    = $this->makeUser();
        $exchange = $this->makePortfolio($user, 'Exchange');
        $wallet   = $this->makePortfolio($user, 'Cold Wallet');
        $btc      = $this->makeAsset('BTC', 50000.0);

        $this->addBuy($exchange, $btc, 0.3, 40000);
        $this->addBuy($wallet,   $btc, 0.5, 42000);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();

        // All Holdings section should appear
        $response->assertSee('All Holdings');

        // Combined quantity: 0.3 + 0.5 = 0.8 BTC
        $response->assertSee('BTC');

        // $allHoldings passed to view
        $allHoldings = $response->viewData('allHoldings');
        $this->assertCount(1, $allHoldings);

        $btcHolding = $allHoldings->first();
        $this->assertEquals(0.8, $btcHolding['quantity']);
        $this->assertEquals(0.3 * 40000 + 0.5 * 42000, $btcHolding['total_cost']); // 12000 + 21000 = 33000
        $this->assertEquals(round(0.8 * 50000, 2), $btcHolding['current_value']);  // 40000.00
    }

    public function test_dashboard_aggregates_multiple_assets(): void
    {
        $user  = $this->makeUser();
        $p1    = $this->makePortfolio($user, 'A');
        $p2    = $this->makePortfolio($user, 'B');
        $btc   = $this->makeAsset('BTC', 50000.0);
        $eth   = $this->makeAsset('ETH', 3000.0);

        $this->addBuy($p1, $btc, 1.0, 40000);
        $this->addBuy($p2, $eth, 2.0, 2000);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $allHoldings = $response->viewData('allHoldings');
        $this->assertCount(2, $allHoldings);

        $symbols = $allHoldings->pluck('asset')->map(fn ($a) => $a->symbol)->all();
        $this->assertContains('BTC', $symbols);
        $this->assertContains('ETH', $symbols);
    }

    public function test_dashboard_all_holdings_empty_when_no_transactions(): void
    {
        $user = $this->makeUser();
        $this->makePortfolio($user, 'Empty');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $allHoldings = $response->viewData('allHoldings');
        $this->assertCount(0, $allHoldings);
        $response->assertDontSee('All Holdings');
    }

    public function test_dashboard_only_shows_current_users_portfolios(): void
    {
        $user  = $this->makeUser();
        $other = $this->makeUser();

        $this->makePortfolio($user,  'Mine');
        $this->makePortfolio($other, 'Theirs');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertSee('Mine');
        $response->assertDontSee('Theirs');
    }

    public function test_all_holdings_pct_sums_to_100_and_is_proportional(): void
    {
        $user = $this->makeUser();
        $p    = $this->makePortfolio($user);
        $btc  = $this->makeAsset('BTC', 50000.0);
        $eth  = $this->makeAsset('ETH', 3000.0);

        $this->addBuy($p, $btc, 1.0, 40000); // value: $50,000
        $this->addBuy($p, $eth, 2.0,  2000); // value: $6,000 — total $56,000

        $response    = $this->actingAs($user)->get(route('dashboard'));
        $allHoldings = $response->viewData('allHoldings');

        $btcRow = $allHoldings->first(fn ($h) => $h['asset']->symbol === 'BTC');
        $ethRow = $allHoldings->first(fn ($h) => $h['asset']->symbol === 'ETH');

        $this->assertEquals(round(50000 / 56000 * 100, 2), $btcRow['pct']);
        $this->assertEquals(round(6000  / 56000 * 100, 2), $ethRow['pct']);
        $this->assertEqualsWithDelta(100.0, $btcRow['pct'] + $ethRow['pct'], 0.1);
    }

    public function test_dashboard_sorted_by_value_descending(): void
    {
        $user  = $this->makeUser();
        $p     = $this->makePortfolio($user);
        $btc   = $this->makeAsset('BTC',  50000.0);
        $eth   = $this->makeAsset('ETH',  3000.0);
        $doge  = $this->makeAsset('DOGE', 0.1);

        $this->addBuy($p, $doge, 100,  0.08);  // value: $10
        $this->addBuy($p, $btc,  1.0, 40000);  // value: $50,000
        $this->addBuy($p, $eth,  2.0,  2000);  // value: $6,000

        $response = $this->actingAs($user)->get(route('dashboard'));

        $allHoldings = $response->viewData('allHoldings');
        $symbols = $allHoldings->pluck('asset')->map(fn ($a) => $a->symbol)->values()->all();

        $this->assertEquals(['BTC', 'ETH', 'DOGE'], $symbols);
    }
}

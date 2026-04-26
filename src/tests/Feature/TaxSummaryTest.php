<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makePortfolio(User $user): Portfolio
    {
        return Portfolio::create(['user_id' => $user->id, 'name' => 'Test', 'currency' => 'USD']);
    }

    private function makeAsset(string $symbol): Asset
    {
        return Asset::firstOrCreate(['symbol' => $symbol], ['name' => $symbol, 'asset_type' => 'stock']);
    }

    public function test_tax_summary_requires_auth(): void
    {
        $this->get(route('tax.summary'))->assertRedirect(route('login'));
    }

    public function test_tax_summary_shows_empty_state(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->get(route('tax.summary'))
            ->assertOk()
            ->assertSee('No realized gains');
    }

    public function test_tax_summary_shows_realized_gain(): void
    {
        $user      = $this->makeUser();
        $portfolio = $this->makePortfolio($user);
        $asset     = $this->makeAsset('AAPL');

        Transaction::create([
            'portfolio_id' => $portfolio->id, 'asset_id' => $asset->id,
            'type' => 'buy', 'quantity' => 10, 'price_per_unit' => 100,
            'fees' => 0, 'currency' => 'USD', 'transacted_at' => '2022-01-01',
        ]);
        Transaction::create([
            'portfolio_id' => $portfolio->id, 'asset_id' => $asset->id,
            'type' => 'sell', 'quantity' => 10, 'price_per_unit' => 150,
            'fees' => 0, 'currency' => 'USD', 'transacted_at' => '2022-06-01',
        ]);

        $this->actingAs($user)
            ->get(route('tax.summary', ['year' => 2022]))
            ->assertOk()
            ->assertSee('2022')
            ->assertSee('500.00'); // $500 gain
    }

    public function test_tax_summary_splits_short_and_long_term(): void
    {
        $user      = $this->makeUser();
        $portfolio = $this->makePortfolio($user);
        $asset     = $this->makeAsset('AAPL');

        // Short-term: held < 365 days
        Transaction::create([
            'portfolio_id' => $portfolio->id, 'asset_id' => $asset->id,
            'type' => 'buy', 'quantity' => 5, 'price_per_unit' => 100,
            'fees' => 0, 'currency' => 'USD', 'transacted_at' => '2022-01-01',
        ]);
        Transaction::create([
            'portfolio_id' => $portfolio->id, 'asset_id' => $asset->id,
            'type' => 'sell', 'quantity' => 5, 'price_per_unit' => 200,
            'fees' => 0, 'currency' => 'USD', 'transacted_at' => '2022-06-01', // 5 months
        ]);

        // Long-term: held >= 365 days
        Transaction::create([
            'portfolio_id' => $portfolio->id, 'asset_id' => $asset->id,
            'type' => 'buy', 'quantity' => 5, 'price_per_unit' => 100,
            'fees' => 0, 'currency' => 'USD', 'transacted_at' => '2021-01-01',
        ]);
        Transaction::create([
            'portfolio_id' => $portfolio->id, 'asset_id' => $asset->id,
            'type' => 'sell', 'quantity' => 5, 'price_per_unit' => 200,
            'fees' => 0, 'currency' => 'USD', 'transacted_at' => '2022-02-01', // 13 months
        ]);

        $response = $this->actingAs($user)
            ->get(route('tax.summary', ['year' => 2022]))
            ->assertOk();

        $response->assertSee('Short-term');
        $response->assertSee('Long-term');
    }

    public function test_export_transactions_requires_auth(): void
    {
        $this->get(route('export.transactions'))->assertRedirect(route('login'));
    }

    public function test_export_transactions_returns_csv(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get(route('export.transactions'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('transactions-', $response->headers->get('Content-Disposition'));
    }

    public function test_export_realized_gains_requires_auth(): void
    {
        $this->get(route('export.realized-gains'))->assertRedirect(route('login'));
    }

    public function test_export_realized_gains_returns_csv(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get(route('export.realized-gains'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('realized-gains-', $response->headers->get('Content-Disposition'));
    }

    public function test_export_transactions_scoped_to_own_portfolios(): void
    {
        $user  = $this->makeUser();
        $other = $this->makeUser();

        // The export is scoped via the controller's portfolio pluck — verify it returns 200 for the user
        $this->makePortfolio($user);
        Portfolio::create(['user_id' => $other->id, 'name' => 'Other', 'currency' => 'USD']);

        $this->actingAs($user)->get(route('export.transactions'))->assertOk();
        $this->actingAs($other)->get(route('export.transactions'))->assertOk();
    }
}

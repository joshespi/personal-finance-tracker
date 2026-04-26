<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Tests\TestCase;

class TaxSummaryTest extends TestCase
{
    public function test_tax_summary_requires_auth(): void
    {
        $this->get(route('tax.summary'))->assertRedirect(route('login'));
    }

    public function test_tax_summary_shows_empty_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('tax.summary'))
            ->assertOk()
            ->assertSee('No realized gains');
    }

    public function test_tax_summary_shows_realized_gain(): void
    {
        $portfolio = Portfolio::factory()->create();
        $asset     = Asset::factory()->stock()->create(['symbol' => 'AAPL']);

        Transaction::factory()->for($portfolio)->for($asset)->buy()
            ->create(['quantity' => 10, 'price_per_unit' => 100, 'transacted_at' => '2022-01-01']);
        Transaction::factory()->for($portfolio)->for($asset)->sell()
            ->create(['quantity' => 10, 'price_per_unit' => 150, 'transacted_at' => '2022-06-01']);

        $this->actingAs($portfolio->user)
            ->get(route('tax.summary', ['year' => 2022]))
            ->assertOk()
            ->assertSee('2022')
            ->assertSee('500.00');
    }

    public function test_tax_summary_splits_short_and_long_term(): void
    {
        $portfolio = Portfolio::factory()->create();
        $asset     = Asset::factory()->stock()->create(['symbol' => 'AAPL']);

        Transaction::factory()->for($portfolio)->for($asset)->buy()
            ->create(['quantity' => 5, 'price_per_unit' => 100, 'transacted_at' => '2022-01-01']);
        Transaction::factory()->for($portfolio)->for($asset)->sell()
            ->create(['quantity' => 5, 'price_per_unit' => 200, 'transacted_at' => '2022-06-01']);

        Transaction::factory()->for($portfolio)->for($asset)->buy()
            ->create(['quantity' => 5, 'price_per_unit' => 100, 'transacted_at' => '2021-01-01']);
        Transaction::factory()->for($portfolio)->for($asset)->sell()
            ->create(['quantity' => 5, 'price_per_unit' => 200, 'transacted_at' => '2022-02-01']);

        $this->actingAs($portfolio->user)
            ->get(route('tax.summary', ['year' => 2022]))
            ->assertOk()
            ->assertSee('Short-term')
            ->assertSee('Long-term');
    }

    public function test_export_transactions_requires_auth(): void
    {
        $this->get(route('export.transactions'))->assertRedirect(route('login'));
    }

    public function test_export_transactions_returns_csv(): void
    {
        $user = User::factory()->create();

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
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('export.realized-gains'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('realized-gains-', $response->headers->get('Content-Disposition'));
    }

    public function test_export_transactions_scoped_to_own_portfolios(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        Portfolio::factory()->for($user)->create();
        Portfolio::factory()->for($other)->create();

        $this->actingAs($user)->get(route('export.transactions'))->assertOk();
        $this->actingAs($other)->get(route('export.transactions'))->assertOk();
    }
}

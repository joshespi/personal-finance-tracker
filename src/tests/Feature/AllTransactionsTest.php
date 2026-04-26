<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Tests\TestCase;

class AllTransactionsTest extends TestCase
{
    public function test_all_transactions_requires_auth(): void
    {
        $this->get(route('transactions.all'))->assertRedirect(route('login'));
    }

    public function test_all_transactions_shows_transactions_from_all_portfolios(): void
    {
        $user  = User::factory()->create();
        $portA = Portfolio::factory()->for($user)->create(['name' => 'Alpha']);
        $portB = Portfolio::factory()->for($user)->create(['name' => 'Beta']);

        Transaction::factory()->for($portA)->for(Asset::factory()->create(['symbol' => 'AAPL']))->create();
        Transaction::factory()->for($portB)->for(Asset::factory()->create(['symbol' => 'GOOG']))->create();

        $this->actingAs($user)
            ->get(route('transactions.all'))
            ->assertOk()
            ->assertSee('AAPL')
            ->assertSee('GOOG')
            ->assertSee('Alpha')
            ->assertSee('Beta');
    }

    public function test_all_transactions_does_not_show_other_users_transactions(): void
    {
        $user      = User::factory()->create();
        $portOwn   = Portfolio::factory()->for($user)->create();
        $portOther = Portfolio::factory()->create();

        Transaction::factory()->for($portOwn)->for(Asset::factory()->create(['symbol' => 'AAPL']))->create();
        Transaction::factory()->for($portOther)->for(Asset::factory()->create(['symbol' => 'GOOG']))->create();

        $this->actingAs($user)
            ->get(route('transactions.all'))
            ->assertOk()
            ->assertSee('AAPL')
            ->assertDontSee('GOOG');
    }

    public function test_all_transactions_filter_by_portfolio(): void
    {
        $user  = User::factory()->create();
        $portA = Portfolio::factory()->for($user)->create();
        $portB = Portfolio::factory()->for($user)->create();

        Transaction::factory()->for($portA)->for(Asset::factory()->create(['symbol' => 'AAPL']))->create();
        Transaction::factory()->for($portB)->for(Asset::factory()->create(['symbol' => 'GOOG']))->create();

        $this->actingAs($user)
            ->get(route('transactions.all', ['portfolio_id' => $portA->id]))
            ->assertOk()
            ->assertSee('AAPL')
            ->assertDontSee('GOOG');
    }

    public function test_all_transactions_filter_by_symbol(): void
    {
        $portfolio = Portfolio::factory()->create();

        Transaction::factory()->for($portfolio)->for(Asset::factory()->create(['symbol' => 'AAPL']))->create();
        Transaction::factory()->for($portfolio)->for(Asset::factory()->create(['symbol' => 'GOOG']))->create();

        $this->actingAs($portfolio->user)
            ->get(route('transactions.all', ['search' => 'AAPL']))
            ->assertOk()
            ->assertSee('AAPL')
            ->assertDontSee('GOOG');
    }

    public function test_all_transactions_filter_by_type(): void
    {
        $portfolio = Portfolio::factory()->create();
        $asset     = Asset::factory()->create(['symbol' => 'AAPL']);

        Transaction::factory()->for($portfolio)->for($asset)->buy()->create(['transacted_at' => '2024-01-15']);
        Transaction::factory()->for($portfolio)->for($asset)->sell()->create(['transacted_at' => '2024-06-20']);

        $this->actingAs($portfolio->user)
            ->get(route('transactions.all', ['type' => 'buy']))
            ->assertOk()
            ->assertSee('Jan 15, 2024')
            ->assertDontSee('Jun 20, 2024');
    }

    public function test_all_transactions_filter_by_date_range(): void
    {
        $portfolio = Portfolio::factory()->create();
        $asset     = Asset::factory()->create(['symbol' => 'AAPL']);

        Transaction::factory()->for($portfolio)->for($asset)->buy()->create(['transacted_at' => '2023-01-01']);
        Transaction::factory()->for($portfolio)->for($asset)->buy()->create(['transacted_at' => '2024-06-01']);

        $this->actingAs($portfolio->user)
            ->get(route('transactions.all', ['from' => '2024-01-01']))
            ->assertOk()
            ->assertSee('Jun 1, 2024')
            ->assertDontSee('Jan 1, 2023');
    }

    public function test_all_transactions_sort_by_symbol(): void
    {
        $portfolio = Portfolio::factory()->create();

        Transaction::factory()->for($portfolio)->for(Asset::factory()->create(['symbol' => 'MSFT']))->create();
        Transaction::factory()->for($portfolio)->for(Asset::factory()->create(['symbol' => 'AAPL']))->create();

        $response = $this->actingAs($portfolio->user)
            ->get(route('transactions.all', ['sort' => 'symbol', 'dir' => 'asc']));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertLessThan(strpos($content, 'MSFT'), strpos($content, 'AAPL'));
    }

    public function test_all_transactions_sort_by_portfolio(): void
    {
        $user  = User::factory()->create();
        $portA = Portfolio::factory()->for($user)->create(['name' => 'Alpha']);
        $portB = Portfolio::factory()->for($user)->create(['name' => 'Beta']);

        Transaction::factory()->for($portB)->for(Asset::factory()->create(['symbol' => 'GOOG']))->create();
        Transaction::factory()->for($portA)->for(Asset::factory()->create(['symbol' => 'AAPL']))->create();

        $response = $this->actingAs($user)
            ->get(route('transactions.all', ['sort' => 'portfolio', 'dir' => 'asc']));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertLessThan(strpos($content, 'Beta'), strpos($content, 'Alpha'));
    }

    public function test_all_transactions_empty_for_user_with_no_portfolios(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('transactions.all'))
            ->assertOk()
            ->assertSee('No transactions found.');
    }

    public function test_all_transactions_shows_portfolio_filter_dropdown(): void
    {
        $portfolio = Portfolio::factory()->create(['name' => 'My Portfolio']);

        $this->actingAs($portfolio->user)
            ->get(route('transactions.all'))
            ->assertOk()
            ->assertSee('My Portfolio');
    }
}

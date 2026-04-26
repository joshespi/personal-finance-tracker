<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllTransactionsTest extends TestCase
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

    private function makeAsset(string $symbol): Asset
    {
        return Asset::firstOrCreate(
            ['symbol' => $symbol],
            ['name' => $symbol, 'asset_type' => 'stock']
        );
    }

    private function addBuy(Portfolio $portfolio, Asset $asset, string $date = '2024-01-01'): Transaction
    {
        return Transaction::create([
            'portfolio_id'   => $portfolio->id,
            'asset_id'       => $asset->id,
            'type'           => 'buy',
            'quantity'       => 1,
            'price_per_unit' => 100,
            'fees'           => 0,
            'currency'       => 'USD',
            'transacted_at'  => $date,
        ]);
    }

    public function test_all_transactions_requires_auth(): void
    {
        $this->get(route('transactions.all'))->assertRedirect(route('login'));
    }

    public function test_all_transactions_shows_transactions_from_all_portfolios(): void
    {
        $user  = $this->makeUser();
        $portA = $this->makePortfolio($user, 'Alpha');
        $portB = $this->makePortfolio($user, 'Beta');

        $this->addBuy($portA, $this->makeAsset('AAPL'));
        $this->addBuy($portB, $this->makeAsset('GOOG'));

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
        $user  = $this->makeUser();
        $other = $this->makeUser();

        $portOwn   = $this->makePortfolio($user,  'Mine');
        $portOther = $this->makePortfolio($other, 'Theirs');

        $this->addBuy($portOwn,   $this->makeAsset('AAPL'));
        $this->addBuy($portOther, $this->makeAsset('GOOG'));

        $this->actingAs($user)
            ->get(route('transactions.all'))
            ->assertOk()
            ->assertSee('AAPL')
            ->assertDontSee('GOOG');
    }

    public function test_all_transactions_filter_by_portfolio(): void
    {
        $user  = $this->makeUser();
        $portA = $this->makePortfolio($user, 'Alpha');
        $portB = $this->makePortfolio($user, 'Beta');

        $this->addBuy($portA, $this->makeAsset('AAPL'));
        $this->addBuy($portB, $this->makeAsset('GOOG'));

        $this->actingAs($user)
            ->get(route('transactions.all', ['portfolio_id' => $portA->id]))
            ->assertOk()
            ->assertSee('AAPL')
            ->assertDontSee('GOOG');
    }

    public function test_all_transactions_filter_by_symbol(): void
    {
        $user = $this->makeUser();
        $port = $this->makePortfolio($user);

        $this->addBuy($port, $this->makeAsset('AAPL'));
        $this->addBuy($port, $this->makeAsset('GOOG'));

        $this->actingAs($user)
            ->get(route('transactions.all', ['search' => 'AAPL']))
            ->assertOk()
            ->assertSee('AAPL')
            ->assertDontSee('GOOG');
    }

    public function test_all_transactions_filter_by_type(): void
    {
        $user  = $this->makeUser();
        $port  = $this->makePortfolio($user);
        $asset = $this->makeAsset('AAPL');

        Transaction::create([
            'portfolio_id' => $port->id, 'asset_id' => $asset->id,
            'type' => 'buy',  'quantity' => 1, 'price_per_unit' => 100,
            'fees' => 0, 'currency' => 'USD', 'transacted_at' => '2024-01-15',
        ]);
        Transaction::create([
            'portfolio_id' => $port->id, 'asset_id' => $asset->id,
            'type' => 'sell', 'quantity' => 1, 'price_per_unit' => 110,
            'fees' => 0, 'currency' => 'USD', 'transacted_at' => '2024-06-20',
        ]);

        $response = $this->actingAs($user)
            ->get(route('transactions.all', ['type' => 'buy']));

        // "Jan 15" appears (buy row); "Jun 20" does not (sell row filtered out)
        $response->assertOk()
            ->assertSee('Jan 15, 2024')
            ->assertDontSee('Jun 20, 2024');
    }

    public function test_all_transactions_filter_by_date_range(): void
    {
        $user  = $this->makeUser();
        $port  = $this->makePortfolio($user);
        $asset = $this->makeAsset('AAPL');

        $this->addBuy($port, $asset, '2023-01-01');
        $this->addBuy($port, $asset, '2024-06-01');

        $this->actingAs($user)
            ->get(route('transactions.all', ['from' => '2024-01-01']))
            ->assertOk()
            ->assertSee('Jun 1, 2024')
            ->assertDontSee('Jan 1, 2023');
    }

    public function test_all_transactions_sort_by_symbol(): void
    {
        $user = $this->makeUser();
        $port = $this->makePortfolio($user);

        $this->addBuy($port, $this->makeAsset('MSFT'));
        $this->addBuy($port, $this->makeAsset('AAPL'));

        $response = $this->actingAs($user)
            ->get(route('transactions.all', ['sort' => 'symbol', 'dir' => 'asc']));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertLessThan(strpos($content, 'MSFT'), strpos($content, 'AAPL'));
    }

    public function test_all_transactions_sort_by_portfolio(): void
    {
        $user  = $this->makeUser();
        $portA = $this->makePortfolio($user, 'Alpha');
        $portB = $this->makePortfolio($user, 'Beta');

        $this->addBuy($portB, $this->makeAsset('GOOG'));
        $this->addBuy($portA, $this->makeAsset('AAPL'));

        $response = $this->actingAs($user)
            ->get(route('transactions.all', ['sort' => 'portfolio', 'dir' => 'asc']));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertLessThan(strpos($content, 'Beta'), strpos($content, 'Alpha'));
    }

    public function test_all_transactions_empty_for_user_with_no_portfolios(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->get(route('transactions.all'))
            ->assertOk()
            ->assertSee('No transactions found.');
    }

    public function test_all_transactions_shows_portfolio_filter_dropdown(): void
    {
        $user = $this->makeUser();
        $this->makePortfolio($user, 'My Portfolio');

        $this->actingAs($user)
            ->get(route('transactions.all'))
            ->assertOk()
            ->assertSee('My Portfolio');
    }
}

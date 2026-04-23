<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortfolioTransferTest extends TestCase
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

    private function transferPayload(Portfolio $from, Portfolio $to, array $overrides = []): array
    {
        return array_merge([
            'from_portfolio_id' => $from->id,
            'to_portfolio_id'   => $to->id,
            'symbol'            => 'BTC',
            'asset_type'        => 'crypto',
            'quantity'          => '0.5',
            'price_per_unit'    => '40000',
            'fees'              => '5',
            'currency'          => 'USD',
            'transacted_at'     => '2024-06-01',
            'notes'             => null,
        ], $overrides);
    }

    public function test_create_page_requires_auth(): void
    {
        $this->get(route('transfers.create'))->assertRedirect(route('login'));
    }

    public function test_store_requires_auth(): void
    {
        // CSRF is disabled globally in TestCase; auth middleware still runs
        $this->post(route('transfers.store'), [])->assertRedirect(route('login'));
    }

    public function test_create_page_shows_form_with_portfolios(): void
    {
        $user = $this->makeUser();
        $this->makePortfolio($user, 'Exchange');
        $this->makePortfolio($user, 'Cold Wallet');

        $response = $this->actingAs($user)->get(route('transfers.create'));

        $response->assertOk();
        $response->assertSee('Exchange');
        $response->assertSee('Cold Wallet');
    }

    public function test_store_creates_linked_transfer_pair(): void
    {
        $user = $this->makeUser();
        $from = $this->makePortfolio($user, 'Exchange');
        $to   = $this->makePortfolio($user, 'Cold Wallet');

        $response = $this->actingAs($user)->post(route('transfers.store'), $this->transferPayload($from, $to));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        $this->assertDatabaseCount('transactions', 2);

        $out = Transaction::where('type', 'transfer_out')->first();
        $in  = Transaction::where('type', 'transfer_in')->first();

        $this->assertNotNull($out);
        $this->assertNotNull($in);
        $this->assertEquals($from->id, $out->portfolio_id);
        $this->assertEquals($to->id, $in->portfolio_id);

        // transfer_in links back to transfer_out; transfer_out has no link
        $this->assertEquals($out->id, $in->linked_transfer_id);
        $this->assertNull($out->linked_transfer_id);
    }

    public function test_store_uppercases_symbol(): void
    {
        $user = $this->makeUser();
        $from = $this->makePortfolio($user, 'A');
        $to   = $this->makePortfolio($user, 'B');

        $this->actingAs($user)->post(route('transfers.store'), $this->transferPayload($from, $to, [
            'symbol'     => 'eth',
            'asset_type' => 'crypto',
        ]));

        $this->assertDatabaseHas('assets', ['symbol' => 'ETH']);
    }

    public function test_store_reuses_existing_asset(): void
    {
        $user  = $this->makeUser();
        $from  = $this->makePortfolio($user, 'A');
        $to    = $this->makePortfolio($user, 'B');
        Asset::create(['symbol' => 'BTC', 'name' => 'Bitcoin', 'asset_type' => 'crypto']);

        $this->actingAs($user)->post(route('transfers.store'), $this->transferPayload($from, $to));

        $this->assertDatabaseCount('assets', 1);
    }

    public function test_store_rejects_same_portfolio(): void
    {
        $user      = $this->makeUser();
        $portfolio = $this->makePortfolio($user);

        $response = $this->actingAs($user)->post(route('transfers.store'), $this->transferPayload($portfolio, $portfolio));

        $response->assertSessionHasErrors('to_portfolio_id');
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_store_rejects_another_users_portfolio(): void
    {
        $user    = $this->makeUser();
        $other   = $this->makeUser();
        $from    = $this->makePortfolio($user);
        $foreign = $this->makePortfolio($other);

        $response = $this->actingAs($user)->post(route('transfers.store'), $this->transferPayload($from, $foreign));

        $response->assertSessionHasErrors('to_portfolio_id');
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_store_validates_required_fields(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->post(route('transfers.store'), []);

        $response->assertSessionHasErrors([
            'from_portfolio_id',
            'to_portfolio_id',
            'symbol',
            'asset_type',
            'quantity',
            'price_per_unit',
            'currency',
            'transacted_at',
        ]);
    }

    public function test_store_validates_positive_quantity(): void
    {
        $user = $this->makeUser();
        $from = $this->makePortfolio($user, 'A');
        $to   = $this->makePortfolio($user, 'B');

        $response = $this->actingAs($user)->post(route('transfers.store'), $this->transferPayload($from, $to, [
            'quantity' => '-1',
        ]));

        $response->assertSessionHasErrors('quantity');
    }

    public function test_linked_relations_point_to_correct_portfolios(): void
    {
        $user = $this->makeUser();
        $from = $this->makePortfolio($user, 'Exchange');
        $to   = $this->makePortfolio($user, 'Cold Wallet');

        $this->actingAs($user)->post(route('transfers.store'), $this->transferPayload($from, $to));

        $out = Transaction::where('type', 'transfer_out')->with('linkedTo.portfolio')->first();
        $in  = Transaction::where('type', 'transfer_in')->with('linkedFrom.portfolio')->first();

        $this->assertEquals($out->id, $in->linkedFrom->id);
        $this->assertEquals($from->id, $in->linkedFrom->portfolio_id);
        $this->assertEquals($in->id, $out->linkedTo->id);
        $this->assertEquals($to->id, $out->linkedTo->portfolio_id);
    }
}

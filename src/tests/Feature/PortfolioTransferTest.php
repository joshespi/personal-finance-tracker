<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Tests\TestCase;

class PortfolioTransferTest extends TestCase
{
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
        $this->post(route('transfers.store'), [])->assertRedirect(route('login'));
    }

    public function test_create_page_shows_form_with_portfolios(): void
    {
        $user = User::factory()->create();
        Portfolio::factory()->for($user)->create(['name' => 'Exchange']);
        Portfolio::factory()->for($user)->create(['name' => 'Cold Wallet']);

        $this->actingAs($user)->get(route('transfers.create'))
            ->assertOk()
            ->assertSee('Exchange')
            ->assertSee('Cold Wallet');
    }

    public function test_store_creates_linked_transfer_pair(): void
    {
        $user = User::factory()->create();
        $from = Portfolio::factory()->for($user)->create();
        $to   = Portfolio::factory()->for($user)->create();

        $this->actingAs($user)->post(route('transfers.store'), $this->transferPayload($from, $to))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('transactions', 2);

        $out = Transaction::where('type', 'transfer_out')->first();
        $in  = Transaction::where('type', 'transfer_in')->first();

        $this->assertEquals($from->id, $out->portfolio_id);
        $this->assertEquals($to->id, $in->portfolio_id);
        $this->assertEquals($out->id, $in->linked_transfer_id);
        $this->assertNull($out->linked_transfer_id);
    }

    public function test_store_uppercases_symbol(): void
    {
        $user = User::factory()->create();
        $from = Portfolio::factory()->for($user)->create();
        $to   = Portfolio::factory()->for($user)->create();

        $this->actingAs($user)->post(route('transfers.store'), $this->transferPayload($from, $to, [
            'symbol'     => 'eth',
            'asset_type' => 'crypto',
        ]));

        $this->assertDatabaseHas('assets', ['symbol' => 'ETH']);
    }

    public function test_store_reuses_existing_asset(): void
    {
        $user = User::factory()->create();
        $from = Portfolio::factory()->for($user)->create();
        $to   = Portfolio::factory()->for($user)->create();
        Asset::factory()->crypto()->create(['symbol' => 'BTC', 'name' => 'Bitcoin']);

        $this->actingAs($user)->post(route('transfers.store'), $this->transferPayload($from, $to));

        $this->assertDatabaseCount('assets', 1);
    }

    public function test_store_rejects_same_portfolio(): void
    {
        $portfolio = Portfolio::factory()->create();

        $this->actingAs($portfolio->user)
            ->post(route('transfers.store'), $this->transferPayload($portfolio, $portfolio))
            ->assertSessionHasErrors('to_portfolio_id');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_store_rejects_another_users_portfolio(): void
    {
        $user    = User::factory()->create();
        $from    = Portfolio::factory()->for($user)->create();
        $foreign = Portfolio::factory()->create();

        $this->actingAs($user)
            ->post(route('transfers.store'), $this->transferPayload($from, $foreign))
            ->assertSessionHasErrors('to_portfolio_id');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_store_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('transfers.store'), [])
            ->assertSessionHasErrors([
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

    public function test_invalid_asset_type_is_rejected(): void
    {
        $user = User::factory()->create();
        $from = Portfolio::factory()->for($user)->create();
        $to   = Portfolio::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('transfers.store'), $this->transferPayload($from, $to, ['asset_type' => 'commodity']))
            ->assertSessionHasErrors('asset_type');
    }

    public function test_store_validates_positive_quantity(): void
    {
        $user = User::factory()->create();
        $from = Portfolio::factory()->for($user)->create();
        $to   = Portfolio::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('transfers.store'), $this->transferPayload($from, $to, ['quantity' => '-1']))
            ->assertSessionHasErrors('quantity');
    }

    public function test_linked_relations_point_to_correct_portfolios(): void
    {
        $user = User::factory()->create();
        $from = Portfolio::factory()->for($user)->create();
        $to   = Portfolio::factory()->for($user)->create();

        $this->actingAs($user)->post(route('transfers.store'), $this->transferPayload($from, $to));

        $out = Transaction::where('type', 'transfer_out')->with('linkedTo.portfolio')->first();
        $in  = Transaction::where('type', 'transfer_in')->with('linkedFrom.portfolio')->first();

        $this->assertEquals($out->id, $in->linkedFrom->id);
        $this->assertEquals($from->id, $in->linkedFrom->portfolio_id);
        $this->assertEquals($in->id, $out->linkedTo->id);
        $this->assertEquals($to->id, $out->linkedTo->portfolio_id);
    }
}

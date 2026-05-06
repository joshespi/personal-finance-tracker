<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\User;
use App\Models\WatchlistItem;
use Tests\TestCase;

class WatchlistTest extends TestCase
{
    public function test_watchlist_requires_auth(): void
    {
        $this->get(route('watchlist.index'))->assertRedirect(route('login'));
    }

    public function test_watchlist_shows_empty_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('watchlist.index'))
            ->assertOk()
            ->assertSee('Your watchlist is empty');
    }

    public function test_can_add_to_watchlist(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('watchlist.store'), [
            'symbol'       => 'aapl',
            'asset_type'   => 'stock',
            'target_price' => '200.00',
            'notes'        => 'Waiting for dip',
        ])->assertRedirect(route('watchlist.index'));

        $this->assertDatabaseHas('watchlist_items', [
            'user_id'    => $user->id,
            'symbol'     => 'AAPL',
            'asset_type' => 'stock',
        ]);
    }

    public function test_watchlist_shows_item_with_current_price(): void
    {
        $user  = User::factory()->create();
        $asset = Asset::factory()->stock()->create(['symbol' => 'TSLA', 'name' => 'Tesla']);
        AssetPrice::factory()->for($asset)->create(['price' => 250.00]);

        WatchlistItem::factory()->for($user)->create([
            'symbol'       => 'TSLA',
            'asset_type'   => 'stock',
            'target_price' => 200.00,
        ]);

        $this->actingAs($user)->get(route('watchlist.index'))
            ->assertOk()
            ->assertSee('TSLA');
    }

    public function test_can_remove_from_watchlist(): void
    {
        $item = WatchlistItem::factory()->create(['symbol' => 'MSFT']);

        $this->actingAs($item->user)->delete(route('watchlist.destroy', $item))
            ->assertRedirect(route('watchlist.index'));

        $this->assertDatabaseMissing('watchlist_items', ['id' => $item->id]);
    }

    public function test_cannot_remove_another_users_watchlist_item(): void
    {
        $item  = WatchlistItem::factory()->create(['symbol' => 'AAPL']);
        $other = User::factory()->create();

        $this->actingAs($other)->delete(route('watchlist.destroy', $item))
            ->assertForbidden();
    }

    public function test_invalid_asset_type_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('watchlist.store'), [
            'symbol'     => 'AAPL',
            'asset_type' => 'commodity',
        ])->assertSessionHasErrors('asset_type');

        $this->assertDatabaseMissing('watchlist_items', ['symbol' => 'AAPL']);
    }

    public function test_adding_duplicate_symbol_updates_existing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('watchlist.store'), [
            'symbol'     => 'NVDA',
            'asset_type' => 'stock',
        ]);

        $this->actingAs($user)->post(route('watchlist.store'), [
            'symbol'       => 'nvda',
            'asset_type'   => 'stock',
            'target_price' => '500',
        ]);

        $this->assertDatabaseCount('watchlist_items', 1);
        $this->assertDatabaseHas('watchlist_items', ['symbol' => 'NVDA', 'target_price' => 500]);
    }
}

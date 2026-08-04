<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserAssetClassification;
use Tests\TestCase;

class AssetReclassifyTest extends TestCase
{
    private function userWithAsset(): array
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->create(['user_id' => $user->id]);
        $asset     = Asset::factory()->stock()->create();
        Transaction::factory()->create(['portfolio_id' => $portfolio->id, 'asset_id' => $asset->id]);

        return [$user, $asset];
    }

    public function test_reclassify_requires_auth(): void
    {
        $asset = Asset::factory()->stock()->create();

        $this->patch(route('assets.reclassify', $asset), ['asset_type' => 'crypto'])
            ->assertRedirect(route('login'));
    }

    public function test_reclassify_stores_per_user_override_and_leaves_global_untouched(): void
    {
        [$user, $asset] = $this->userWithAsset();

        $this->actingAs($user)
            ->patch(route('assets.reclassify', $asset), ['asset_type' => 'crypto'])
            ->assertRedirect();

        $this->assertDatabaseHas('user_asset_classifications', [
            'user_id'    => $user->id,
            'asset_id'   => $asset->id,
            'asset_type' => 'crypto',
        ]);
        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'asset_type' => 'stock']);
    }

    public function test_reclassify_is_per_user(): void
    {
        $asset = Asset::factory()->stock()->create(['symbol' => 'ZZZ']);

        $alice = User::factory()->create();
        $aPort = Portfolio::factory()->for($alice)->create();
        Transaction::factory()->for($aPort)->for($asset)->buy()->create(['quantity' => 1, 'price_per_unit' => 100]);

        $bob   = User::factory()->create();
        $bPort = Portfolio::factory()->for($bob)->create();
        Transaction::factory()->for($bPort)->for($asset)->buy()->create(['quantity' => 1, 'price_per_unit' => 100]);

        $this->actingAs($alice)
            ->patch(route('assets.reclassify', $asset), ['asset_type' => 'crypto'])
            ->assertRedirect();

        $aliceHoldings = $this->actingAs($alice)->get(route('dashboard'))->viewData('allHoldings');
        $this->assertEquals('crypto', $aliceHoldings->firstWhere('asset.symbol', 'ZZZ')['asset']->asset_type);

        $bobHoldings = $this->actingAs($bob)->get(route('dashboard'))->viewData('allHoldings');
        $this->assertEquals('stock', $bobHoldings->firstWhere('asset.symbol', 'ZZZ')['asset']->asset_type);
    }

    public function test_reclassify_updates_existing_override(): void
    {
        [$user, $asset] = $this->userWithAsset();

        $this->actingAs($user)->patch(route('assets.reclassify', $asset), ['asset_type' => 'crypto']);
        $this->actingAs($user)->patch(route('assets.reclassify', $asset), ['asset_type' => 'bond']);

        $this->assertEquals(
            1,
            UserAssetClassification::where('user_id', $user->id)->where('asset_id', $asset->id)->count()
        );
        $this->assertDatabaseHas('user_asset_classifications', [
            'user_id'    => $user->id,
            'asset_id'   => $asset->id,
            'asset_type' => 'bond',
        ]);
    }

    public function test_user_cannot_reclassify_asset_they_do_not_hold(): void
    {
        $user  = User::factory()->create();
        $asset = Asset::factory()->stock()->create(); // no transaction for this user

        $this->actingAs($user)
            ->patch(route('assets.reclassify', $asset), ['asset_type' => 'crypto'])
            ->assertForbidden();

        $this->assertDatabaseMissing('user_asset_classifications', ['asset_id' => $asset->id]);
    }

    public function test_reclassify_validates_asset_type(): void
    {
        [$user, $asset] = $this->userWithAsset();

        $this->actingAs($user)
            ->patch(route('assets.reclassify', $asset), ['asset_type' => 'invalid'])
            ->assertSessionHasErrors('asset_type');

        $this->assertDatabaseMissing('user_asset_classifications', ['asset_id' => $asset->id]);
    }

    public function test_empty_request_makes_no_changes(): void
    {
        [$user, $asset] = $this->userWithAsset();

        $this->actingAs($user)
            ->patch(route('assets.reclassify', $asset), [])
            ->assertRedirect();

        $this->assertEquals('stock', $asset->fresh()->asset_type);
        $this->assertNull($asset->fresh()->price_source);
        $this->assertDatabaseMissing('user_asset_classifications', ['asset_id' => $asset->id]);
    }

    public function test_admin_can_set_price_source(): void
    {
        [$user, $asset] = $this->userWithAsset();
        $user->forceFill(['is_admin' => true])->save();

        $this->actingAs($user)
            ->patch(route('assets.reclassify', $asset), ['price_source' => 'finnhub'])
            ->assertRedirect();

        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'price_source' => 'finnhub']);
    }

    public function test_non_admin_cannot_set_price_source(): void
    {
        [$user, $asset] = $this->userWithAsset();

        $this->actingAs($user)
            ->patch(route('assets.reclassify', $asset), ['price_source' => 'finnhub'])
            ->assertForbidden();

        $this->assertNull($asset->fresh()->price_source);
    }

    public function test_admin_can_reset_price_source_to_auto(): void
    {
        [$user, $asset] = $this->userWithAsset();
        $user->forceFill(['is_admin' => true])->save();
        $asset->update(['price_source' => 'finnhub']);

        $this->actingAs($user)
            ->patch(route('assets.reclassify', $asset), ['price_source' => ''])
            ->assertRedirect();

        $this->assertNull($asset->fresh()->price_source);
    }

    public function test_price_source_rejects_invalid_values(): void
    {
        [$user, $asset] = $this->userWithAsset();

        $this->actingAs($user)
            ->patch(route('assets.reclassify', $asset), ['price_source' => 'polygon'])
            ->assertSessionHasErrors('price_source');
    }

    public function test_reclassify_flashes_success_message(): void
    {
        [$user, $asset] = $this->userWithAsset();

        $this->actingAs($user)
            ->patch(route('assets.reclassify', $asset), ['asset_type' => 'crypto'])
            ->assertSessionHas('success');
    }

    public function test_transaction_edit_page_shows_asset_type_dropdown(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();
        $asset     = Asset::factory()->stock()->create();
        $tx        = Transaction::factory()->for($portfolio)->for($asset)->create();

        $this->actingAs($user)
            ->get(route('transactions.edit', $tx))
            ->assertOk()
            ->assertSee(route('assets.reclassify', $asset), false)
            ->assertSee('crypto')
            ->assertSee('real_estate');
    }
}

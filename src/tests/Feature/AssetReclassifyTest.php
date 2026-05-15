<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
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

    public function test_authenticated_user_can_reclassify_asset(): void
    {
        [$user, $asset] = $this->userWithAsset();

        $this->actingAs($user)
            ->patch(route('assets.reclassify', $asset), ['asset_type' => 'crypto'])
            ->assertRedirect();

        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'asset_type' => 'crypto']);
    }

    public function test_user_cannot_reclassify_asset_they_do_not_hold(): void
    {
        $user  = User::factory()->create();
        $asset = Asset::factory()->stock()->create(); // no transaction for this user

        $this->actingAs($user)
            ->patch(route('assets.reclassify', $asset), ['asset_type' => 'crypto'])
            ->assertForbidden();
    }

    public function test_reclassify_validates_asset_type(): void
    {
        [$user, $asset] = $this->userWithAsset();

        $this->actingAs($user)
            ->patch(route('assets.reclassify', $asset), ['asset_type' => 'invalid'])
            ->assertSessionHasErrors('asset_type');

        $this->assertEquals('stock', $asset->fresh()->asset_type);
    }

    public function test_reclassify_requires_asset_type(): void
    {
        [$user, $asset] = $this->userWithAsset();

        $this->actingAs($user)
            ->patch(route('assets.reclassify', $asset), [])
            ->assertSessionHasErrors('asset_type');
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

<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetReclassifyTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeAsset(string $symbol, string $type = 'stock'): Asset
    {
        return Asset::create(['symbol' => $symbol, 'name' => $symbol, 'asset_type' => $type]);
    }

    public function test_reclassify_requires_auth(): void
    {
        $asset = $this->makeAsset('ARKB', 'stock');

        $this->patch(route('assets.reclassify', $asset), ['asset_type' => 'crypto'])
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_reclassify_asset(): void
    {
        $user  = $this->makeUser();
        $asset = $this->makeAsset('ARKB', 'stock');

        $this->actingAs($user)
            ->patch(route('assets.reclassify', $asset), ['asset_type' => 'crypto'])
            ->assertRedirect();

        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'asset_type' => 'crypto']);
    }

    public function test_reclassify_stock_to_crypto(): void
    {
        $user  = $this->makeUser();
        $asset = $this->makeAsset('ARKB', 'stock');

        $this->actingAs($user)
            ->patch(route('assets.reclassify', $asset), ['asset_type' => 'crypto']);

        $this->assertEquals('crypto', $asset->fresh()->asset_type);
    }

    public function test_reclassify_crypto_to_stock(): void
    {
        $user  = $this->makeUser();
        $asset = $this->makeAsset('BTC', 'crypto');

        $this->actingAs($user)
            ->patch(route('assets.reclassify', $asset), ['asset_type' => 'stock']);

        $this->assertEquals('stock', $asset->fresh()->asset_type);
    }

    public function test_reclassify_validates_asset_type(): void
    {
        $user  = $this->makeUser();
        $asset = $this->makeAsset('ARKB', 'stock');

        $this->actingAs($user)
            ->patch(route('assets.reclassify', $asset), ['asset_type' => 'invalid'])
            ->assertSessionHasErrors('asset_type');

        $this->assertEquals('stock', $asset->fresh()->asset_type);
    }

    public function test_reclassify_requires_asset_type(): void
    {
        $user  = $this->makeUser();
        $asset = $this->makeAsset('ARKB', 'stock');

        $this->actingAs($user)
            ->patch(route('assets.reclassify', $asset), [])
            ->assertSessionHasErrors('asset_type');
    }

    public function test_reclassify_flashes_success_message(): void
    {
        $user  = $this->makeUser();
        $asset = $this->makeAsset('ARKB', 'stock');

        $this->actingAs($user)
            ->patch(route('assets.reclassify', $asset), ['asset_type' => 'crypto'])
            ->assertSessionHas('success');
    }
}

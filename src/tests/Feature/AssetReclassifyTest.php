<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use Tests\TestCase;

class AssetReclassifyTest extends TestCase
{
    public function test_reclassify_requires_auth(): void
    {
        $asset = Asset::factory()->stock()->create();

        $this->patch(route('assets.reclassify', $asset), ['asset_type' => 'crypto'])
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_reclassify_asset(): void
    {
        $user  = User::factory()->create();
        $asset = Asset::factory()->stock()->create();

        $this->actingAs($user)
            ->patch(route('assets.reclassify', $asset), ['asset_type' => 'crypto'])
            ->assertRedirect();

        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'asset_type' => 'crypto']);
    }

    public function test_reclassify_validates_asset_type(): void
    {
        $user  = User::factory()->create();
        $asset = Asset::factory()->stock()->create();

        $this->actingAs($user)
            ->patch(route('assets.reclassify', $asset), ['asset_type' => 'invalid'])
            ->assertSessionHasErrors('asset_type');

        $this->assertEquals('stock', $asset->fresh()->asset_type);
    }

    public function test_reclassify_requires_asset_type(): void
    {
        $user  = User::factory()->create();
        $asset = Asset::factory()->stock()->create();

        $this->actingAs($user)
            ->patch(route('assets.reclassify', $asset), [])
            ->assertSessionHasErrors('asset_type');
    }

    public function test_reclassify_flashes_success_message(): void
    {
        $user  = User::factory()->create();
        $asset = Asset::factory()->stock()->create();

        $this->actingAs($user)
            ->patch(route('assets.reclassify', $asset), ['asset_type' => 'crypto'])
            ->assertSessionHas('success');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Liability;
use App\Models\LiabilityBalance;
use App\Models\ManualAsset;
use App\Models\ManualValuation;
use App\Models\Portfolio;
use App\Models\User;
use Tests\TestCase;

class ManualAssetTest extends TestCase
{
    public function test_create_with_cost_basis(): void
    {
        $portfolio = Portfolio::factory()->create();

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.manual-assets.store', $portfolio), [
                'name'        => '123 Main St',
                'asset_class' => 'real_estate',
                'cost_basis'  => 200000,
                'currency'    => 'USD',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('manual_assets', [
            'name'       => '123 Main St',
            'cost_basis' => 200000,
        ]);
    }

    public function test_cost_basis_is_optional(): void
    {
        $portfolio = Portfolio::factory()->create();

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.manual-assets.store', $portfolio), [
                'name'        => 'Watch',
                'asset_class' => 'collectible',
                'currency'    => 'USD',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('manual_assets', [
            'name'       => 'Watch',
            'cost_basis' => null,
        ]);
    }

    public function test_cost_basis_rejects_negative(): void
    {
        $portfolio = Portfolio::factory()->create();

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.manual-assets.store', $portfolio), [
                'name'        => 'Bad',
                'asset_class' => 'other',
                'cost_basis'  => -1,
                'currency'    => 'USD',
            ])
            ->assertSessionHasErrors('cost_basis');
    }

    public function test_update_cost_basis(): void
    {
        $asset = ManualAsset::factory()->create(['cost_basis' => null]);

        $this->actingAs($asset->portfolio->user)
            ->put(route('manual-assets.update', $asset), [
                'name'        => $asset->name,
                'asset_class' => $asset->asset_class,
                'cost_basis'  => 175000,
                'currency'    => $asset->currency,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('manual_assets', [
            'id'         => $asset->id,
            'cost_basis' => 175000,
        ]);
    }

    public function test_profit_loss_returns_null_without_cost_basis(): void
    {
        $asset = ManualAsset::factory()->create(['cost_basis' => null]);
        ManualValuation::factory()->for($asset)->create(['value' => 250000]);

        $this->assertNull($asset->fresh()->profitLoss());
    }

    public function test_profit_loss_returns_null_without_valuation(): void
    {
        $asset = ManualAsset::factory()->create(['cost_basis' => 200000]);

        $this->assertNull($asset->profitLoss());
    }

    public function test_profit_loss_computes_difference(): void
    {
        $asset = ManualAsset::factory()->create(['cost_basis' => 200000]);
        ManualValuation::factory()->for($asset)->create(['value' => 250000]);

        $this->assertSame(50000.0, $asset->fresh()->profitLoss());
    }

    public function test_show_page_displays_linked_liability_and_equity(): void
    {
        $asset     = ManualAsset::factory()->create(['asset_class' => 'real_estate']);
        $liability = Liability::factory()->for($asset->portfolio->user)->create([
            'name'            => 'Home Mortgage',
            'liability_type'  => 'mortgage',
            'manual_asset_id' => $asset->id,
            'currency'        => 'USD',
        ]);
        LiabilityBalance::factory()->for($liability)->create(['balance' => 300000]);
        ManualValuation::factory()->for($asset)->create(['value' => 500000]);

        $this->actingAs($asset->portfolio->user)
            ->get(route('manual-assets.show', $asset))
            ->assertOk()
            ->assertSee('Home Mortgage')
            ->assertSee('300,000.00')
            ->assertSee('Equity')
            ->assertSee('200,000.00');
    }

    public function test_show_page_hides_liability_section_when_none_linked(): void
    {
        $asset = ManualAsset::factory()->create();

        $this->actingAs($asset->portfolio->user)
            ->get(route('manual-assets.show', $asset))
            ->assertOk()
            ->assertDontSee('Linked Liabilities');
    }

    public function test_show_page_displays_profit_loss(): void
    {
        $asset = ManualAsset::factory()->create(['cost_basis' => 200000]);
        ManualValuation::factory()->for($asset)->create(['value' => 250000]);

        $this->actingAs($asset->portfolio->user)
            ->get(route('manual-assets.show', $asset))
            ->assertOk()
            ->assertSee('Profit / Loss')
            ->assertSee('+50,000.00');
    }
}

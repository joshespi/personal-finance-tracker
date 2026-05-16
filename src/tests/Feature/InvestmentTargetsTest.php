<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Tests\TestCase;

class InvestmentTargetsTest extends TestCase
{
    public function test_targets_page_requires_auth(): void
    {
        $this->patch(route('profile.targets'), [])->assertRedirect(route('login'));
    }

    public function test_user_can_set_investment_targets(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.targets'), [
                'target_stock_pct'       => 60,
                'target_crypto_pct'      => 20,
                'target_real_estate_pct' => 10,
                'target_bond_pct'        => 10,
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertEquals(60, $user->fresh()->target_stock_pct);
        $this->assertEquals(20, $user->fresh()->target_crypto_pct);
    }

    public function test_targets_must_sum_to_100_when_non_zero(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.targets'), [
                'target_stock_pct'       => 50,
                'target_crypto_pct'      => 20,
                'target_real_estate_pct' => 10,
                'target_bond_pct'        => 10,
            ])
            ->assertSessionHasErrorsIn('investmentTargets');
    }

    public function test_targets_all_zero_is_valid(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.targets'), [
                'target_stock_pct'       => 0,
                'target_crypto_pct'      => 0,
                'target_real_estate_pct' => 0,
                'target_bond_pct'        => 0,
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertEquals(0, $user->fresh()->target_stock_pct);
    }

    public function test_targets_must_not_exceed_100_each(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.targets'), [
                'target_stock_pct'       => 110,
                'target_crypto_pct'      => 0,
                'target_real_estate_pct' => 0,
                'target_bond_pct'        => 0,
            ])
            ->assertSessionHasErrorsIn('investmentTargets', 'target_stock_pct');
    }

    public function test_dashboard_shows_rebalancing_when_targets_and_holdings_exist(): void
    {
        $user      = User::factory()->create([
            'target_stock_pct'       => 60,
            'target_crypto_pct'      => 20,
            'target_real_estate_pct' => 10,
            'target_bond_pct'        => 10,
        ]);
        $portfolio = Portfolio::factory()->for($user)->create();
        $asset     = Asset::factory()->stock()->create(['symbol' => 'VOO']);
        AssetPrice::factory()->for($asset)->create(['price' => 500, 'recorded_at' => now()]);
        Transaction::factory()->buy()->for($portfolio)->for($asset)->create([
            'quantity' => 10, 'price_per_unit' => 490, 'fees' => 0,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Global Rebalancing');
    }

    public function test_dashboard_hides_rebalancing_when_no_targets(): void
    {
        $user = User::factory()->create([
            'target_stock_pct'       => 0,
            'target_crypto_pct'      => 0,
            'target_real_estate_pct' => 0,
            'target_bond_pct'        => 0,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Global Rebalancing');
    }
}

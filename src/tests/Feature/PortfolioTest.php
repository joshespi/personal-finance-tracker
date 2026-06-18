<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\ManualAsset;
use App\Models\Portfolio;
use App\Models\User;
use Tests\TestCase;

class PortfolioTest extends TestCase
{
    public function test_index_requires_auth(): void
    {
        $this->get(route('portfolios.index'))->assertRedirect(route('login'));
    }

    public function test_create_page_requires_auth(): void
    {
        $this->get(route('portfolios.create'))->assertRedirect(route('login'));
    }

    public function test_index_shows_user_portfolios(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        Portfolio::factory()->for($user)->create(['name' => 'My Stocks']);
        Portfolio::factory()->for($other)->create(['name' => 'Other Stocks']);

        $this->actingAs($user)
            ->get(route('portfolios.index'))
            ->assertOk()
            ->assertSee('My Stocks')
            ->assertDontSee('Other Stocks');
    }

    public function test_can_create_portfolio(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('portfolios.store'), [
                'name'        => 'Tech Portfolio',
                'description' => 'Growth stocks',
                'currency'    => 'USD',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('portfolios', [
            'user_id'  => $user->id,
            'name'     => 'Tech Portfolio',
            'currency' => 'USD',
        ]);
    }

    public function test_tax_advantaged_flag_stored_on_create(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('portfolios.store'), [
                'name'              => '401k',
                'currency'          => 'USD',
                'is_tax_advantaged' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(
            Portfolio::where('user_id', $user->id)->where('name', '401k')->sole()->is_tax_advantaged
        );
    }

    public function test_store_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('portfolios.store'), [])
            ->assertSessionHasErrors(['name', 'currency']);
    }

    public function test_show_requires_ownership(): void
    {
        $owner     = User::factory()->create();
        $other     = User::factory()->create();
        $portfolio = Portfolio::factory()->for($owner)->create();

        $this->actingAs($other)
            ->get(route('portfolios.show', $portfolio))
            ->assertForbidden();
    }

    public function test_can_update_portfolio(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create(['name' => 'Old Name']);

        $this->actingAs($user)
            ->patch(route('portfolios.update', $portfolio), [
                'name'     => 'New Name',
                'currency' => 'USD',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('portfolios.show', $portfolio));

        $this->assertSame('New Name', $portfolio->fresh()->name);
    }

    public function test_cannot_update_other_users_portfolio(): void
    {
        $owner     = User::factory()->create();
        $other     = User::factory()->create();
        $portfolio = Portfolio::factory()->for($owner)->create();

        $this->actingAs($other)
            ->patch(route('portfolios.update', $portfolio), [
                'name'     => 'Hacked',
                'currency' => 'USD',
            ])
            ->assertForbidden();
    }

    public function test_can_delete_portfolio(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();

        $this->actingAs($user)
            ->delete(route('portfolios.destroy', $portfolio))
            ->assertRedirect(route('portfolios.index'));

        $this->assertNull($portfolio->fresh());
    }

    public function test_cannot_delete_other_users_portfolio(): void
    {
        $owner     = User::factory()->create();
        $other     = User::factory()->create();
        $portfolio = Portfolio::factory()->for($owner)->create();

        $this->actingAs($other)
            ->delete(route('portfolios.destroy', $portfolio))
            ->assertForbidden();

        $this->assertNotNull($portfolio->fresh());
    }

    public function test_show_loads_proxy_ticker_manual_asset_without_lazy_loading(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();
        $proxy     = Asset::factory()->stock()->create(['symbol' => 'SPY']);
        AssetPrice::factory()->for($proxy)->create(['price' => 500.00, 'recorded_at' => now()]);
        ManualAsset::factory()->proxyTracked($proxy, 50000.0, '2026-01-01', 100.0)->for($portfolio)->create();

        $this->actingAs($user)
            ->get(route('portfolios.show', $portfolio))
            ->assertOk();
    }
}

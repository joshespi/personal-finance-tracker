<?php

namespace Tests\Feature;

use App\Models\Liability;
use App\Models\LiabilityBalance;
use App\Models\ManualAsset;
use App\Models\Portfolio;
use App\Models\User;
use Tests\TestCase;

class LiabilityTest extends TestCase
{
    public function test_index_requires_auth(): void
    {
        $this->get(route('liabilities.index'))->assertRedirect(route('login'));
    }

    public function test_index_returns_ok_when_empty(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('liabilities.index'))
            ->assertOk()
            ->assertSee('No liabilities tracked yet.');
    }

    public function test_create_liability(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('liabilities.store'), [
                'name'           => 'Visa Card',
                'liability_type' => 'credit_card',
                'currency'       => 'USD',
                'interest_rate'  => 22.99,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('liabilities', [
            'user_id'        => $user->id,
            'name'           => 'Visa Card',
            'liability_type' => 'credit_card',
        ]);
    }

    public function test_create_liability_linked_to_manual_asset(): void
    {
        $portfolio = Portfolio::factory()->create();
        $asset     = ManualAsset::factory()->for($portfolio)->create();

        $this->actingAs($portfolio->user)
            ->post(route('liabilities.store'), [
                'name'            => 'Mortgage',
                'liability_type'  => 'mortgage',
                'manual_asset_id' => $asset->id,
                'currency'        => 'USD',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('liabilities', [
            'name'            => 'Mortgage',
            'manual_asset_id' => $asset->id,
        ]);
    }

    public function test_cannot_link_to_other_users_manual_asset(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->create();
        $asset     = ManualAsset::factory()->for($portfolio)->create();

        $this->actingAs($user)
            ->post(route('liabilities.store'), [
                'name'            => 'Mortgage',
                'liability_type'  => 'mortgage',
                'manual_asset_id' => $asset->id,
                'currency'        => 'USD',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('liabilities', ['user_id' => $user->id]);
    }

    public function test_validation_rejects_invalid_type(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('liabilities.store'), [
                'name'           => 'Foo',
                'liability_type' => 'not_a_real_type',
                'currency'       => 'USD',
            ])
            ->assertSessionHasErrors('liability_type');
    }

    public function test_show_forbidden_for_other_user(): void
    {
        $liability = Liability::factory()->create();
        $other     = User::factory()->create();

        $this->actingAs($other)
            ->get(route('liabilities.show', $liability))
            ->assertForbidden();
    }

    public function test_update_liability(): void
    {
        $liability = Liability::factory()->create(['name' => 'Old Name', 'liability_type' => 'other']);

        $this->actingAs($liability->user)
            ->put(route('liabilities.update', $liability), [
                'name'           => 'New Name',
                'liability_type' => 'auto_loan',
                'currency'       => 'USD',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('liabilities', ['id' => $liability->id, 'name' => 'New Name', 'liability_type' => 'auto_loan']);
    }

    public function test_delete_liability(): void
    {
        $liability = Liability::factory()->create();

        $this->actingAs($liability->user)
            ->delete(route('liabilities.destroy', $liability))
            ->assertRedirect(route('liabilities.index'));

        $this->assertDatabaseMissing('liabilities', ['id' => $liability->id]);
    }

    public function test_record_balance(): void
    {
        $liability = Liability::factory()->create(['liability_type' => 'mortgage']);

        $this->actingAs($liability->user)
            ->post(route('liabilities.balances.store', $liability), [
                'balance'     => 245000,
                'recorded_at' => '2026-04-26',
            ])
            ->assertRedirect(route('liabilities.show', $liability));

        $this->assertDatabaseHas('liability_balances', [
            'liability_id' => $liability->id,
            'balance'      => 245000,
        ]);
    }

    public function test_balance_forbidden_for_other_user(): void
    {
        $liability = Liability::factory()->create();
        $other     = User::factory()->create();

        $this->actingAs($other)
            ->post(route('liabilities.balances.store', $liability), [
                'balance'     => 100,
                'recorded_at' => '2026-04-26',
            ])
            ->assertForbidden();
    }

    public function test_delete_balance(): void
    {
        $liability = Liability::factory()->create();
        $balance   = LiabilityBalance::factory()->for($liability)->create(['balance' => 100000]);

        $this->actingAs($liability->user)
            ->delete(route('liabilities.balances.destroy', $balance))
            ->assertRedirect(route('liabilities.show', $liability));

        $this->assertDatabaseMissing('liability_balances', ['id' => $balance->id]);
    }

    public function test_cannot_delete_another_users_liability_balance(): void
    {
        $liability = Liability::factory()->create();
        $balance   = LiabilityBalance::factory()->for($liability)->create(['balance' => 100000]);
        $other     = User::factory()->create();

        $this->actingAs($other)
            ->delete(route('liabilities.balances.destroy', $balance))
            ->assertForbidden();

        $this->assertDatabaseHas('liability_balances', ['id' => $balance->id]);
    }

    public function test_cascade_delete_balances_when_liability_deleted(): void
    {
        $liability = Liability::factory()->create();
        LiabilityBalance::factory()->for($liability)->create();

        $liability->delete();

        $this->assertDatabaseMissing('liability_balances', ['liability_id' => $liability->id]);
    }

    public function test_dashboard_shows_net_worth_tile_when_debt_exists(): void
    {
        $user = User::factory()->create();
        Portfolio::factory()->for($user)->create();
        $liability = Liability::factory()->for($user)->create();
        LiabilityBalance::factory()->for($liability)->create(['balance' => 250000]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Net Worth');
    }

    public function test_dashboard_hides_net_worth_tile_when_no_debt(): void
    {
        $user = User::factory()->create();
        Portfolio::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Net Worth');
    }
}

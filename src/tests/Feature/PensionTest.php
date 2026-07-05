<?php

namespace Tests\Feature;

use App\Models\Pension;
use App\Models\User;
use Tests\TestCase;

class PensionTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name'                 => 'URS Pension',
            'plan_label'           => 'Tier 1 Public Employees',
            'service_credit_years' => 16.588,
            'multiplier_pct'       => 2.0,
            'final_average_salary' => 100000,
            'birth_year'           => now()->year - 40,
            'retirement_age'       => 52,
            'life_expectancy_age'  => 90,
            'discount_rate_pct'    => 2.0,
            'cola_pct'             => 4.0,
            'currency'             => 'USD',
            'include_in_net_worth' => '1',
        ], $overrides);
    }

    public function test_index_requires_auth(): void
    {
        $this->get(route('pensions.index'))->assertRedirect(route('login'));
    }

    public function test_index_shows_empty_state(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('pensions.index'))
            ->assertOk()
            ->assertSee('No pension tracked yet.');
    }

    public function test_create_pension(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('pensions.store'), $this->payload())
            ->assertRedirect(route('pensions.index'));

        $this->assertDatabaseHas('pensions', [
            'user_id'              => $user->id,
            'name'                 => 'URS Pension',
            'service_credit_years' => 16.588,
            'multiplier_pct'       => 2.000,
        ]);
    }

    public function test_owner_can_view_create_form(): void
    {
        // Renders create.blade.php + the shared _form partial with $pension = null.
        $this->actingAs(User::factory()->create())
            ->get(route('pensions.create'))
            ->assertOk()
            ->assertSee('Add Pension');
    }

    public function test_owner_can_view_edit_form(): void
    {
        // Renders edit.blade.php + the shared _form partial with a persisted pension.
        $user    = User::factory()->create();
        $pension = Pension::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('pensions.edit', $pension))
            ->assertOk()
            ->assertSee('Edit Pension');
    }

    public function test_index_renders_income_floor_and_accrual(): void
    {
        $user = User::factory()->create();
        Pension::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('pensions.index'))
            ->assertOk()
            ->assertSee('Retirement Income Floor')
            ->assertSee('What your pension is worth today');
    }

    public function test_sensitivity_overrides_are_accepted(): void
    {
        $user = User::factory()->create();
        Pension::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('pensions.index', ['discount_rate' => 4, 'swr' => 3.5, 'annual_expenses' => 60000]))
            ->assertOk();
    }

    public function test_update_pension(): void
    {
        $user    = User::factory()->create();
        $pension = Pension::factory()->for($user)->create();

        $this->actingAs($user)
            ->put(route('pensions.update', $pension), $this->payload(['name' => 'Renamed Pension']))
            ->assertRedirect(route('pensions.index'));

        $this->assertDatabaseHas('pensions', ['id' => $pension->id, 'name' => 'Renamed Pension']);
    }

    public function test_delete_pension(): void
    {
        $user    = User::factory()->create();
        $pension = Pension::factory()->for($user)->create();

        $this->actingAs($user)
            ->delete(route('pensions.destroy', $pension))
            ->assertRedirect(route('pensions.index'));

        $this->assertDatabaseMissing('pensions', ['id' => $pension->id]);
    }

    public function test_cannot_touch_another_users_pension(): void
    {
        $owner   = User::factory()->create();
        $other   = User::factory()->create();
        $pension = Pension::factory()->for($owner)->create();

        $this->actingAs($other)->get(route('pensions.edit', $pension))->assertForbidden();
        $this->actingAs($other)->put(route('pensions.update', $pension), $this->payload())->assertForbidden();
        $this->actingAs($other)->delete(route('pensions.destroy', $pension))->assertForbidden();
    }

    public function test_dashboard_shows_pension_value_and_income_when_included(): void
    {
        $user = User::factory()->create();
        Pension::factory()->for($user)->create(['include_in_net_worth' => true]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pension Value')       // lump-sum PV tile (in net worth)
            ->assertSee('Retirement Income');  // monthly income tile
    }

    public function test_dashboard_hides_pension_value_but_still_shows_income_when_excluded(): void
    {
        $user = User::factory()->create();
        Pension::factory()->for($user)->create(['include_in_net_worth' => false]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Pension Value')   // PV excluded from net worth → no PV tile
            ->assertSee('Retirement Income');  // income is informational → still shown
    }

    public function test_demo_mode_masks_pension_name_and_figures(): void
    {
        $user = User::factory()->create();
        Pension::factory()->for($user)->create(['name' => 'URS Pension']);

        $this->actingAs($user)
            ->withSession(['demo_mode' => true])
            ->get(route('pensions.index'))
            ->assertOk()
            ->assertSee('••••')          // dollar figures are masked
            ->assertDontSee('URS Pension'); // the pension name is pseudonymized
    }
}

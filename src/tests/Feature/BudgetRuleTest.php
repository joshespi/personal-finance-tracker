<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Envelope;
use App\Models\EnvelopeTransaction;
use App\Models\User;
use App\Services\BudgetRuleService;
use Tests\TestCase;

class BudgetRuleTest extends TestCase
{
    public function test_index_requires_auth(): void
    {
        $this->get(route('budget-rule'))->assertRedirect(route('login'));
    }

    public function test_shows_empty_state_when_no_income(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('budget-rule'))
            ->assertOk()
            ->assertSee('No income recorded');
    }

    public function test_ratios_compute_against_income_average_and_drift_clear_within_targets(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();
        // $6000 income / 6 = $1000 monthly avg
        CashTransaction::factory()->for($account)->deposit()->create([
            'amount' => 6000, 'occurred_at' => now()->toDateString(),
        ]);

        $mandatory = Envelope::factory()->for($user)->create(['is_mandatory' => true]);
        $savings   = Envelope::factory()->for($user)->create(['is_savings' => true]);

        // $3000 mandatory spend / 6 = $500 (50%)
        CashTransaction::factory()->for($account)->spend($mandatory)->create([
            'amount' => 3000, 'occurred_at' => now()->toDateString(),
        ]);
        // $1500 net savings funding / 6 = $250 (25%)
        EnvelopeTransaction::factory()->for($savings)->fund()->create([
            'amount' => 1500, 'occurred_at' => now()->toDateString(),
        ]);

        $data = (new BudgetRuleService())->compute($user->fresh());

        $this->assertSame(1000.0, $data['monthly_income']);
        $this->assertSame(500.0, $data['monthly_mandatory']);
        $this->assertSame(250.0, $data['monthly_savings']);
        $this->assertSame(250.0, $data['monthly_discretionary']);
        $this->assertSame(50.0, $data['ratios']['mandatory']);
        $this->assertSame(25.0, $data['ratios']['discretionary']);
        $this->assertSame(25.0, $data['ratios']['savings']);
        $this->assertFalse($data['drift']['mandatory_over']);
        $this->assertFalse($data['drift']['savings_under']);
    }

    public function test_drift_fires_when_mandatory_over_50(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();
        CashTransaction::factory()->for($account)->deposit()->create(['amount' => 6000, 'occurred_at' => now()->toDateString()]);

        $mandatory = Envelope::factory()->for($user)->create(['is_mandatory' => true]);
        // $4200 / 6 = $700 = 70% of $1000 income
        CashTransaction::factory()->for($account)->spend($mandatory)->create([
            'amount' => 4200, 'occurred_at' => now()->toDateString(),
        ]);

        $data = (new BudgetRuleService())->compute($user->fresh());

        $this->assertTrue($data['drift']['mandatory_over']);
    }

    public function test_drift_fires_when_savings_under_20(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();
        CashTransaction::factory()->for($account)->deposit()->create(['amount' => 6000, 'occurred_at' => now()->toDateString()]);

        $savings = Envelope::factory()->for($user)->create(['is_savings' => true]);
        // $600 / 6 = $100 = 10% of $1000 income (under 20%)
        EnvelopeTransaction::factory()->for($savings)->fund()->create([
            'amount' => 600, 'occurred_at' => now()->toDateString(),
        ]);

        $data = (new BudgetRuleService())->compute($user->fresh());

        $this->assertTrue($data['drift']['savings_under']);
    }

    public function test_emergency_fund_envelope_counts_as_savings(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();
        CashTransaction::factory()->for($account)->deposit()->create(['amount' => 6000, 'occurred_at' => now()->toDateString()]);

        // EF envelope without explicit is_savings flag should still count
        $ef = Envelope::factory()->for($user)->create(['is_emergency_fund' => true]);
        EnvelopeTransaction::factory()->for($ef)->fund()->create([
            'amount' => 1500, 'occurred_at' => now()->toDateString(),
        ]);

        $data = (new BudgetRuleService())->compute($user->fresh());

        $this->assertSame(250.0, $data['monthly_savings']);
    }

    public function test_phase_is_building_when_ef_below_target(): void
    {
        $user    = User::factory()->create(['emergency_fund_target_months' => 6]);
        $account = CashAccount::factory()->for($user)->create();
        CashTransaction::factory()->for($account)->deposit()->create(['amount' => 6000, 'occurred_at' => now()->toDateString()]);

        $mandatory = Envelope::factory()->for($user)->create(['is_mandatory' => true]);
        CashTransaction::factory()->for($account)->spend($mandatory)->create(['amount' => 3000, 'occurred_at' => now()->toDateString()]);
        // baseline = $500/mo, target (6mo) = $3000

        $ef = Envelope::factory()->for($user)->create(['is_emergency_fund' => true]);
        EnvelopeTransaction::factory()->for($ef)->fund()->create(['amount' => 1000, 'occurred_at' => now()->toDateString()]);

        $data = (new BudgetRuleService())->compute($user->fresh());

        $this->assertSame('building', $data['phase']);
        $this->assertFalse($data['emergency_funded']);
        $this->assertSame(3000.0, $data['emergency_target']);
        $this->assertSame(1000.0, $data['emergency_balance']);
    }

    public function test_phase_is_funded_when_ef_meets_target(): void
    {
        $user    = User::factory()->create(['emergency_fund_target_months' => 3]);
        $account = CashAccount::factory()->for($user)->create();
        CashTransaction::factory()->for($account)->deposit()->create(['amount' => 6000, 'occurred_at' => now()->toDateString()]);

        $mandatory = Envelope::factory()->for($user)->create(['is_mandatory' => true]);
        CashTransaction::factory()->for($account)->spend($mandatory)->create(['amount' => 3000, 'occurred_at' => now()->toDateString()]);
        // baseline = $500/mo, target (3mo) = $1500

        $ef = Envelope::factory()->for($user)->create(['is_emergency_fund' => true]);
        EnvelopeTransaction::factory()->for($ef)->fund()->create(['amount' => 1500, 'occurred_at' => now()->toDateString()]);

        $data = (new BudgetRuleService())->compute($user->fresh());

        $this->assertSame('funded', $data['phase']);
        $this->assertTrue($data['emergency_funded']);
    }

    public function test_target_months_setting_changes_target(): void
    {
        $user12  = User::factory()->create(['emergency_fund_target_months' => 12]);
        $account = CashAccount::factory()->for($user12)->create();
        CashTransaction::factory()->for($account)->deposit()->create(['amount' => 6000, 'occurred_at' => now()->toDateString()]);

        $m = Envelope::factory()->for($user12)->create(['is_mandatory' => true]);
        CashTransaction::factory()->for($account)->spend($m)->create(['amount' => 3000, 'occurred_at' => now()->toDateString()]);

        $data = (new BudgetRuleService())->compute($user12->fresh());
        // baseline $500 * 12 = $6000 target
        $this->assertSame(6000.0, $data['emergency_target']);
        $this->assertSame(12, $data['target_months']);
    }

    public function test_window_excludes_old_transactions(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();
        CashTransaction::factory()->for($account)->deposit()->create([
            'amount' => 99999, 'occurred_at' => now()->subMonths(8)->toDateString(),
        ]);

        $data = (new BudgetRuleService())->compute($user->fresh());

        $this->assertSame(0.0, $data['monthly_income']);
        $this->assertFalse($data['has_data']);
    }

    public function test_cross_user_data_does_not_leak(): void
    {
        $user        = User::factory()->create();
        $other       = User::factory()->create();
        $otherAccount = CashAccount::factory()->for($other)->create();
        CashTransaction::factory()->for($otherAccount)->deposit()->create(['amount' => 6000, 'occurred_at' => now()->toDateString()]);

        $env = Envelope::factory()->for($other)->create(['is_mandatory' => true]);
        CashTransaction::factory()->for($otherAccount)->spend($env)->create(['amount' => 3000, 'occurred_at' => now()->toDateString()]);

        $data = (new BudgetRuleService())->compute($user->fresh());

        $this->assertSame(0.0, $data['monthly_income']);
        $this->assertSame(0.0, $data['monthly_mandatory']);
    }

    public function test_envelope_form_persists_is_savings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('envelopes.store'), [
            'name'       => 'Brokerage',
            'color'      => '#10b981',
            'is_savings' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('envelopes', [
            'user_id'    => $user->id,
            'name'       => 'Brokerage',
            'is_savings' => true,
        ]);
    }

    public function test_envelope_update_can_clear_is_savings(): void
    {
        $user = User::factory()->create();
        $env  = Envelope::factory()->for($user)->create(['is_savings' => true, 'color' => '#10b981']);

        $this->actingAs($user)->put(route('envelopes.update', $env), [
            'name'  => $env->name,
            'color' => '#10b981',
        ])->assertRedirect();

        $this->assertDatabaseHas('envelopes', ['id' => $env->id, 'is_savings' => false]);
    }

    public function test_emergency_fund_flag_forces_is_savings_true(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('envelopes.store'), [
            'name'              => 'Cushion',
            'color'             => '#6366f1',
            'is_emergency_fund' => '1',
            // is_savings deliberately omitted
        ])->assertRedirect();

        $this->assertDatabaseHas('envelopes', [
            'user_id'           => $user->id,
            'name'              => 'Cushion',
            'is_emergency_fund' => true,
            'is_savings'        => true,
        ]);
    }

    public function test_profile_update_persists_target_months(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name'                         => $user->name,
            'email'                        => $user->email,
            'emergency_fund_target_months' => 12,
        ])->assertRedirect();

        $this->assertSame(12, (int) $user->fresh()->emergency_fund_target_months);
    }

    public function test_profile_rejects_invalid_target_months(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name'                         => $user->name,
            'email'                        => $user->email,
            'emergency_fund_target_months' => 7,
        ])->assertSessionHasErrors('emergency_fund_target_months');
    }

    public function test_dashboard_shows_drift_banner_when_drift_exists(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();
        CashTransaction::factory()->for($account)->deposit()->create(['amount' => 6000, 'occurred_at' => now()->toDateString()]);

        $m = Envelope::factory()->for($user)->create(['is_mandatory' => true]);
        CashTransaction::factory()->for($account)->spend($m)->create(['amount' => 4200, 'occurred_at' => now()->toDateString()]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('50/30/20 drift');
    }

    public function test_dashboard_hides_drift_banner_when_no_drift(): void
    {
        $user = User::factory()->create();

        // No income → has_data false → drift suppressed
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('50/30/20 drift');
    }
}

<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Envelope;
use App\Models\EnvelopeTransaction;
use App\Models\ScheduledTransaction;
use App\Models\User;
use Tests\TestCase;

class EmergencyFundTest extends TestCase
{
    public function test_index_requires_auth(): void
    {
        $this->get(route('emergency-fund'))->assertRedirect(route('login'));
    }

    public function test_shows_empty_state_when_no_mandatory_envelopes(): void
    {
        $user = User::factory()->create();
        Envelope::factory()->for($user)->create(['is_mandatory' => false]);

        $this->actingAs($user)
            ->get(route('emergency-fund'))
            ->assertOk()
            ->assertSee('No mandatory expenses configured');
    }

    public function test_shows_baseline_from_mandatory_envelope_spend(): void
    {
        $user     = User::factory()->create();
        $account  = CashAccount::factory()->for($user)->create();
        $envelope = Envelope::factory()->for($user)->create(['is_mandatory' => true]);
        CashTransaction::factory()->for($account)->spend($envelope)->create([
            'amount'      => 600,
            'occurred_at' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('emergency-fund'))
            ->assertOk()
            ->assertSee($envelope->name);
    }

    public function test_non_mandatory_envelopes_excluded_from_baseline(): void
    {
        $user         = User::factory()->create();
        $account      = CashAccount::factory()->for($user)->create();
        $mandatory    = Envelope::factory()->for($user)->create(['name' => 'Rent', 'is_mandatory' => true]);
        $nonMandatory = Envelope::factory()->for($user)->create(['name' => 'Dining Out', 'is_mandatory' => false]);

        CashTransaction::factory()->for($account)->spend($mandatory)->create(['amount' => 1000, 'occurred_at' => now()->toDateString()]);
        CashTransaction::factory()->for($account)->spend($nonMandatory)->create(['amount' => 500, 'occurred_at' => now()->toDateString()]);

        $this->actingAs($user)
            ->get(route('emergency-fund'))
            ->assertOk()
            ->assertSee('Rent')
            ->assertSee('1,000');  // baseline includes mandatory spend only, not the $500 Dining Out
    }

    public function test_emergency_fund_envelope_balance_shown(): void
    {
        $user      = User::factory()->create();
        $account   = CashAccount::factory()->for($user)->create();
        $savings   = Envelope::factory()->for($user)->create(['name' => 'Emergency Savings', 'is_emergency_fund' => true]);
        $mandatory = Envelope::factory()->for($user)->create(['is_mandatory' => true]);

        EnvelopeTransaction::factory()->for($savings)->fund()->create(['amount' => 3000, 'occurred_at' => now()->toDateString()]);
        CashTransaction::factory()->for($account)->spend($mandatory)->create(['amount' => 500, 'occurred_at' => now()->toDateString()]);

        $this->actingAs($user)
            ->get(route('emergency-fund'))
            ->assertOk()
            ->assertSee('Emergency Savings')
            ->assertSee('3,000.00');
    }

    public function test_no_cross_user_data_leakage(): void
    {
        $user    = User::factory()->create();
        $other   = User::factory()->create();
        $account = CashAccount::factory()->for($other)->create();
        $env     = Envelope::factory()->for($other)->create(['name' => 'Other Rent', 'is_mandatory' => true]);
        CashTransaction::factory()->for($account)->spend($env)->create(['amount' => 900, 'occurred_at' => now()->toDateString()]);

        $this->actingAs($user)
            ->get(route('emergency-fund'))
            ->assertOk()
            ->assertDontSee('Other Rent');
    }

    public function test_is_emergency_fund_flag_exclusive_on_update(): void
    {
        $user   = User::factory()->create();
        $first  = Envelope::factory()->for($user)->create(['is_emergency_fund' => true]);
        $second = Envelope::factory()->for($user)->create(['name' => 'New Savings', 'color' => '#ff0000']);

        $this->actingAs($user)->put(route('envelopes.update', $second), [
            'name'              => 'New Savings',
            'color'             => '#ff0000',
            'is_emergency_fund' => '1',
        ]);

        $this->assertDatabaseHas('envelopes', ['id' => $first->id, 'is_emergency_fund' => false]);
        $this->assertDatabaseHas('envelopes', ['id' => $second->id, 'is_emergency_fund' => true]);
    }

    public function test_fund_transactions_not_counted_in_baseline(): void
    {
        $user     = User::factory()->create();
        $envelope = Envelope::factory()->for($user)->create(['is_mandatory' => true]);
        EnvelopeTransaction::factory()->for($envelope)->fund()->create(['amount' => 1000, 'occurred_at' => now()->toDateString()]);

        // Mandatory envelope with only fund transactions contributes $0 to baseline → empty state
        $this->actingAs($user)
            ->get(route('emergency-fund'))
            ->assertOk()
            ->assertSee('No mandatory expenses configured');
    }

    public function test_is_emergency_fund_exclusive_on_store(): void
    {
        $user     = User::factory()->create();
        $existing = Envelope::factory()->for($user)->create(['is_emergency_fund' => true]);

        $this->actingAs($user)->post(route('envelopes.store'), [
            'name'              => 'New Fund',
            'color'             => '#6366f1',
            'is_emergency_fund' => '1',
        ]);

        $this->assertDatabaseHas('envelopes', ['id' => $existing->id, 'is_emergency_fund' => false]);
    }

    public function test_spend_older_than_6_months_excluded_from_baseline(): void
    {
        $user     = User::factory()->create();
        $account  = CashAccount::factory()->for($user)->create();
        $envelope = Envelope::factory()->for($user)->create(['is_mandatory' => true]);
        CashTransaction::factory()->for($account)->spend($envelope)->create([
            'amount'      => 999,
            'occurred_at' => now()->subMonths(7)->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('emergency-fund'))
            ->assertOk()
            ->assertSee('No mandatory expenses configured');
    }

    public function test_baseline_calculation_averages_over_6_months(): void
    {
        $user     = User::factory()->create();
        $account  = CashAccount::factory()->for($user)->create();
        $envelope = Envelope::factory()->for($user)->create(['is_mandatory' => true]);

        // $600 spend this month only → avg = $600 / 6 = $100/mo
        CashTransaction::factory()->for($account)->spend($envelope)->create([
            'amount'      => 600,
            'occurred_at' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('emergency-fund'))
            ->assertOk()
            ->assertSee('100.00');
    }

    public function test_scheduled_recurring_spend_drives_baseline_at_full_monthly_value(): void
    {
        $user     = User::factory()->create();
        $account  = CashAccount::factory()->for($user)->create();
        $envelope = Envelope::factory()->for($user)->create(['name' => 'House', 'is_mandatory' => true]);

        // Only one mortgage payment recorded → 6-month average would dilute it to $148.84.
        CashTransaction::factory()->for($account)->spend($envelope)->create([
            'amount'      => 893.04,
            'occurred_at' => now()->toDateString(),
        ]);

        // ...but an active monthly scheduled spend says the real obligation is $893.04/mo.
        ScheduledTransaction::factory()->for($user)->envelopeSpend()->create([
            'envelope_id'     => $envelope->id,
            'cash_account_id' => $account->id,
            'amount'          => 893.04,
            'recurrence'      => 'monthly',
        ]);

        $this->actingAs($user)
            ->get(route('emergency-fund'))
            ->assertOk()
            ->assertSee('893.04')        // baseline uses full monthly value
            ->assertSee('2,679.12');     // 3-month target = 893.04 * 3
    }

    public function test_non_mandatory_envelope_opted_in_counts_toward_baseline(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();
        // Groceries: not a Necessity, but explicitly opted into the emergency-fund target.
        $envelope = Envelope::factory()->for($user)->create([
            'name'                      => 'Groceries',
            'is_mandatory'              => false,
            'include_in_emergency_fund' => true,
        ]);
        CashTransaction::factory()->for($account)->spend($envelope)->create([
            'amount'      => 600,
            'occurred_at' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('emergency-fund'))
            ->assertOk()
            ->assertSee('Groceries')
            ->assertSee('100.00');  // 600 / 6 months
    }

    public function test_opt_in_flag_does_not_make_envelope_a_50_pct_need(): void
    {
        $user     = User::factory()->create();
        $envelope = Envelope::factory()->for($user)->create([
            'is_mandatory'              => false,
            'include_in_emergency_fund' => true,
        ]);

        // Category stays out of "Mandatory" so the budget-rule classification is untouched.
        $this->assertNotSame('Mandatory', $envelope->category());
    }

    public function test_inactive_scheduled_spend_does_not_drive_baseline(): void
    {
        $user     = User::factory()->create();
        $account  = CashAccount::factory()->for($user)->create();
        $envelope = Envelope::factory()->for($user)->create(['is_mandatory' => true]);

        CashTransaction::factory()->for($account)->spend($envelope)->create([
            'amount'      => 600,
            'occurred_at' => now()->toDateString(),
        ]);
        ScheduledTransaction::factory()->for($user)->envelopeSpend()->inactive()->create([
            'envelope_id'     => $envelope->id,
            'cash_account_id' => $account->id,
            'amount'          => 5000,
            'recurrence'      => 'monthly',
        ]);

        // Inactive schedule ignored → falls back to the $600/6 = $100 average.
        $this->actingAs($user)
            ->get(route('emergency-fund'))
            ->assertOk()
            ->assertSee('100.00');
    }
}

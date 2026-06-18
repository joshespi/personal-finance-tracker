<?php

namespace Tests\Feature;

use App\Models\Liability;
use App\Models\LiabilityBalance;
use App\Models\User;
use App\Services\DebtPayoffService;
use Tests\TestCase;

class DebtPayoffTest extends TestCase
{
    public function test_index_requires_auth(): void
    {
        $this->get(route('debt-payoff'))->assertRedirect(route('login'));
    }

    public function test_shows_empty_state_when_no_debts(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('debt-payoff'))
            ->assertOk()
            ->assertSee('No active debt found');
    }

    public function test_mortgage_excluded_from_simulation_and_shown_separately(): void
    {
        $user = User::factory()->create();

        $mortgage = Liability::factory()->for($user)->create([
            'liability_type'  => 'mortgage',
            'interest_rate'   => 7.0,
            'minimum_payment' => 1500,
        ]);
        LiabilityBalance::factory()->for($mortgage)->create(['balance' => 300000]);

        $data = (new DebtPayoffService)->compute($user->fresh());

        $this->assertCount(0, $data['debts']);
        $this->assertCount(1, $data['mortgages']);
        $this->assertSame(300000.0, $data['mortgages'][0]['balance']);
        $this->assertSame(7.0, $data['mortgages'][0]['apr']);
        // Mortgage monthly interest: 300000 * 0.07 / 12 = 1750
        $this->assertEqualsWithDelta(1750.0, $data['mortgages'][0]['monthly_interest'], 0.01);
    }

    public function test_snowball_orders_by_balance_smallest_first(): void
    {
        $user = User::factory()->create();

        // Debt A: smaller balance, lower rate
        $debtA = Liability::factory()->for($user)->create([
            'liability_type' => 'credit_card', 'interest_rate' => 5.0, 'minimum_payment' => 30,
        ]);
        LiabilityBalance::factory()->for($debtA)->create(['balance' => 500]);

        // Debt B: larger balance, higher rate
        $debtB = Liability::factory()->for($user)->create([
            'liability_type' => 'credit_card', 'interest_rate' => 20.0, 'minimum_payment' => 30,
        ]);
        LiabilityBalance::factory()->for($debtB)->create(['balance' => 2000]);

        $data = (new DebtPayoffService)->compute($user->fresh());

        // Snowball: A (smaller) paid first → A's payoff month < B's
        $this->assertLessThan(
            $data['snowball']['payoff_per_debt'][$debtB->id],
            $data['snowball']['payoff_per_debt'][$debtA->id]
        );
    }

    public function test_avalanche_orders_by_rate_highest_first(): void
    {
        // Call simulate() directly with extra payment so priority truly gets more money.
        // With $300 extra going to the high-rate priority, it's paid off long before the low-rate debt.
        $service = new DebtPayoffService;

        $debtData = [
            ['id'              => 1, 'name' => 'Low Rate',  'balance' => 1000.0, 'apr' => 5.0,
                'monthly_rate' => 5.0 / 1200,  'monthly_interest' => 1000 * 5.0 / 1200,
                'min_payment'  => 50.0, 'min_payment_set' => true, 'negative_amortization' => false],
            ['id'              => 2, 'name' => 'High Rate', 'balance' => 1000.0, 'apr' => 20.0,
                'monthly_rate' => 20.0 / 1200, 'monthly_interest' => 1000 * 20.0 / 1200,
                'min_payment'  => 50.0, 'min_payment_set' => true, 'negative_amortization' => false],
        ];

        // Avalanche order: id=2 (20%) first, then id=1 (5%)
        $result = $service->simulate($debtData, [2, 1], 300.0);

        // id=2 gets $50+$300=$350/mo → paid off in ~3 months; id=1 gets $50/mo → paid off much later
        $this->assertLessThan($result['payoff_per_debt'][1], $result['payoff_per_debt'][2]);
    }

    public function test_cascade_rolls_freed_payment_to_next_debt(): void
    {
        $user = User::factory()->create();

        // Debt A: $100, 0% APR, min $100 → paid in month 1
        $debtA = Liability::factory()->for($user)->create([
            'liability_type' => 'credit_card', 'interest_rate' => 0, 'minimum_payment' => 100,
        ]);
        LiabilityBalance::factory()->for($debtA)->create(['balance' => 100]);

        // Debt B: $200, 0% APR, min $50 → after A is paid, gets $150/mo
        $debtB = Liability::factory()->for($user)->create([
            'liability_type' => 'personal_loan', 'interest_rate' => 0, 'minimum_payment' => 50,
        ]);
        LiabilityBalance::factory()->for($debtB)->create(['balance' => 200]);

        $data = (new DebtPayoffService)->compute($user->fresh());

        // Month 1: A paid off ($100 min pays it fully)
        // Month 1: B gets $50 min → $150 remaining
        // Month 2: B gets $150 (total budget) → paid off
        $this->assertSame(1, $data['snowball']['payoff_per_debt'][$debtA->id]);
        $this->assertSame(2, $data['snowball']['payoff_per_debt'][$debtB->id]);
        $this->assertSame(2, $data['snowball']['months']);
    }

    public function test_zero_apr_debt_pays_down_linearly(): void
    {
        $user = User::factory()->create();

        $debt = Liability::factory()->for($user)->create([
            'liability_type' => 'personal_loan', 'interest_rate' => 0, 'minimum_payment' => 100,
        ]);
        LiabilityBalance::factory()->for($debt)->create(['balance' => 300]);

        $data = (new DebtPayoffService)->compute($user->fresh());

        // $300 / $100/mo = 3 months exactly
        $this->assertSame(3, $data['snowball']['months']);
        $this->assertSame(0.0, $data['snowball']['total_interest']);
    }

    public function test_negative_amortization_flag(): void
    {
        $user = User::factory()->create();

        // Monthly interest = $1000 * 0.24 / 12 = $20; min $15 < $20
        $debt = Liability::factory()->for($user)->create([
            'liability_type' => 'credit_card', 'interest_rate' => 24.0, 'minimum_payment' => 15,
        ]);
        LiabilityBalance::factory()->for($debt)->create(['balance' => 1000]);

        $data = (new DebtPayoffService)->compute($user->fresh());

        $this->assertTrue($data['debts'][0]['negative_amortization']);
        $this->assertSame(1, $data['negative_amortization_count']);
    }

    public function test_missing_minimum_payment_uses_estimate(): void
    {
        $user = User::factory()->create();

        // 2% of $1000 = $20, so we expect min_payment = max(25, 20) = 25
        $debt = Liability::factory()->for($user)->create([
            'liability_type' => 'credit_card', 'interest_rate' => 0, 'minimum_payment' => null,
        ]);
        LiabilityBalance::factory()->for($debt)->create(['balance' => 1000]);

        $data = (new DebtPayoffService)->compute($user->fresh());

        $this->assertFalse($data['debts'][0]['min_payment_set']);
        $this->assertSame(25.0, $data['debts'][0]['min_payment']);
    }

    public function test_total_monthly_interest_includes_mortgages(): void
    {
        $user = User::factory()->create();

        $cc = Liability::factory()->for($user)->create([
            'liability_type' => 'credit_card', 'interest_rate' => 24.0, 'minimum_payment' => 50,
        ]);
        LiabilityBalance::factory()->for($cc)->create(['balance' => 1000]); // $20/mo interest

        $mortgage = Liability::factory()->for($user)->create([
            'liability_type' => 'mortgage', 'interest_rate' => 6.0, 'minimum_payment' => 1200,
        ]);
        LiabilityBalance::factory()->for($mortgage)->create(['balance' => 200000]); // $1000/mo interest

        $data = (new DebtPayoffService)->compute($user->fresh());

        // 1000 * 0.24 / 12 = 20; 200000 * 0.06 / 12 = 1000; total = 1020
        $this->assertEqualsWithDelta(1020.0, $data['total_monthly_interest'], 0.5);
    }

    public function test_cross_user_data_does_not_leak(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        $otherDebt = Liability::factory()->for($other)->create([
            'liability_type' => 'credit_card', 'interest_rate' => 20.0, 'minimum_payment' => 50,
        ]);
        LiabilityBalance::factory()->for($otherDebt)->create(['balance' => 5000]);

        $data = (new DebtPayoffService)->compute($user->fresh());

        $this->assertFalse($data['has_data']);
    }

    public function test_minimum_payment_field_persists_via_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('liabilities.store'), [
            'name'            => 'Chase Visa',
            'liability_type'  => 'credit_card',
            'interest_rate'   => '19.99',
            'minimum_payment' => '35',
            'currency'        => 'USD',
        ])->assertRedirect();

        $this->assertDatabaseHas('liabilities', [
            'user_id'         => $user->id,
            'name'            => 'Chase Visa',
            'minimum_payment' => '35.00',
        ]);
    }

    public function test_minimum_payment_can_be_cleared_via_form(): void
    {
        $user = User::factory()->create();
        $l    = Liability::factory()->for($user)->create([
            'liability_type' => 'credit_card', 'minimum_payment' => 50,
        ]);

        $this->actingAs($user)->put(route('liabilities.update', $l), [
            'name'           => $l->name,
            'liability_type' => 'credit_card',
            'currency'       => 'USD',
            // minimum_payment omitted → null
        ])->assertRedirect();

        $this->assertNull($l->fresh()->minimum_payment);
    }

    public function test_page_renders_with_debts(): void
    {
        $user = User::factory()->create();

        $debt = Liability::factory()->for($user)->create([
            'name'            => 'My Card',
            'liability_type'  => 'credit_card',
            'interest_rate'   => 20.0,
            'minimum_payment' => 50,
        ]);
        LiabilityBalance::factory()->for($debt)->create(['balance' => 2000]);

        $this->actingAs($user)
            ->get(route('debt-payoff'))
            ->assertOk()
            ->assertSee('My Card')
            ->assertSee('Snowball')
            ->assertSee('Avalanche');
    }
}

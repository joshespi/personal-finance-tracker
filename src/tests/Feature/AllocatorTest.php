<?php

namespace Tests\Feature;

use App\Models\Envelope;
use App\Models\EnvelopeTransaction;
use App\Models\Liability;
use App\Models\LiabilityBalance;
use App\Models\User;
use Tests\TestCase;

class AllocatorTest extends TestCase
{
    public function test_allocator_requires_auth(): void
    {
        $this->get(route('allocator'))->assertRedirect(route('login'));
    }

    public function test_allocator_shows_form_without_amount(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('allocator'))
            ->assertOk()
            ->assertSee('Extra cash to allocate')
            ->assertDontSee('Remainder');
    }

    public function test_validates_amount(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('allocator', ['amount' => -100]))
            ->assertSessionHasErrors('amount');
    }

    public function test_allocates_to_emergency_fund_gap_first(): void
    {
        $user = User::factory()->create(['emergency_fund_target_months' => 3]);

        // Mandatory envelope funded $600/mo over 6 months → target = 3 * $600 = $1800
        $mandatoryEnv = Envelope::factory()->for($user)->create(['is_mandatory' => true, 'is_emergency_fund' => false]);
        foreach (range(0, 5) as $i) {
            EnvelopeTransaction::factory()->for($mandatoryEnv)->fund()->create([
                'amount'      => 600,
                'occurred_at' => now()->startOfMonth()->subMonths($i),
            ]);
        }

        // EF envelope with $0 balance
        Envelope::factory()->for($user)->create(['is_emergency_fund' => true, 'is_mandatory' => false]);

        $this->actingAs($user)
            ->get(route('allocator', ['amount' => 2000]))
            ->assertOk()
            ->assertSee('Emergency')
            ->assertSee('1,800.00') // target / allocated
            ->assertSee('200.00');  // remainder
    }

    public function test_fully_funded_ef_is_skipped(): void
    {
        $user = User::factory()->create(['emergency_fund_target_months' => 3]);

        $mandatoryEnv = Envelope::factory()->for($user)->create(['is_mandatory' => true, 'is_emergency_fund' => false]);
        foreach (range(0, 5) as $i) {
            EnvelopeTransaction::factory()->for($mandatoryEnv)->fund()->create([
                'amount' => 500, 'occurred_at' => now()->startOfMonth()->subMonths($i),
            ]);
        }

        // EF envelope with enough balance (target = 3 * $500 = $1500, balance = $2000)
        $efEnv = Envelope::factory()->for($user)->create(['is_emergency_fund' => true, 'is_mandatory' => false]);
        EnvelopeTransaction::factory()->fund()->for($efEnv)->create(['amount' => 2000]);

        // EF funded → nothing to allocate, "invest" message shown instead
        $this->actingAs($user)
            ->get(route('allocator', ['amount' => 500]))
            ->assertOk()
            ->assertSee('Nothing to allocate to');
    }

    public function test_allocates_to_highest_apr_debt_first(): void
    {
        $user = User::factory()->create();

        $lowApr  = Liability::factory()->for($user)->create(['interest_rate' => 5.00, 'name' => 'Low APR Card']);
        $highApr = Liability::factory()->for($user)->create(['interest_rate' => 24.99, 'name' => 'High APR Card']);
        LiabilityBalance::factory()->for($lowApr)->create(['balance' => 1000]);
        LiabilityBalance::factory()->for($highApr)->create(['balance' => 500]);

        // $700 covers the $500 high-APR card fully then $200 of the low-APR card
        $response = $this->actingAs($user)
            ->get(route('allocator', ['amount' => 700]))
            ->assertOk()
            ->assertSee('High APR Card')
            ->assertSee('Low APR Card');

        $content = $response->content();

        $this->assertLessThan(
            strpos($content, 'Low APR Card'),
            strpos($content, 'High APR Card')
        );
    }

    public function test_allocates_to_savings_goals(): void
    {
        $user = User::factory()->create();

        $env = Envelope::factory()->for($user)->create([
            'name'        => 'Vacation Fund',
            'goal_amount' => 1000,
            'goal_date'   => now()->addYear()->format('Y-m-d'),
        ]);
        EnvelopeTransaction::factory()->fund()->for($env)->create(['amount' => 400]);

        $this->actingAs($user)
            ->get(route('allocator', ['amount' => 200]))
            ->assertOk()
            ->assertSee('Vacation Fund')
            ->assertSee('200.00'); // full amount goes to goal
    }

    public function test_remainder_shown_when_all_gaps_filled(): void
    {
        $user = User::factory()->create();

        $liability = Liability::factory()->for($user)->create(['interest_rate' => 20.0]);
        LiabilityBalance::factory()->for($liability)->create(['balance' => 300]);

        $this->actingAs($user)
            ->get(route('allocator', ['amount' => 1000]))
            ->assertOk()
            ->assertSee('Remainder')
            ->assertSee('700.00'); // 1000 - 300 remaining
    }

    public function test_fully_clear_shows_invest_message(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('allocator', ['amount' => 500]))
            ->assertOk()
            ->assertSee('Nothing to allocate to');
    }

    public function test_cross_user_isolation(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $lib = Liability::factory()->for($userB)->create(['interest_rate' => 25.0, 'name' => 'Other User Debt']);
        LiabilityBalance::factory()->for($lib)->create(['balance' => 500]);

        $this->actingAs($userA)
            ->get(route('allocator', ['amount' => 200]))
            ->assertOk()
            ->assertDontSee('Other User Debt');
    }
}

<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\User;
use Tests\TestCase;

class PlanningTest extends TestCase
{
    public function test_requires_auth(): void
    {
        $this->get(route('planning'))->assertRedirect(route('login'));
    }

    public function test_default_tab_renders_debt_payoff(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('planning'))
            ->assertOk();
    }

    public function test_allocator_tab_renders(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('planning', ['tab' => 'allocator']))
            ->assertOk();
    }

    public function test_allocator_tab_computes_with_amount(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('planning', ['tab' => 'allocator', 'amount' => 500]))
            ->assertOk();
    }

    public function test_emergency_fund_tab_renders(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('planning', ['tab' => 'emergency-fund']))
            ->assertOk();
    }

    public function test_retirement_tab_renders(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('planning', ['tab' => 'retirement']))
            ->assertOk();
    }

    public function test_retirement_tab_computes_result_with_custom_inputs(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();
        CashTransaction::factory()->for($account, 'cashAccount')->deposit()->create([
            'amount'      => 5000,
            'occurred_at' => now()->subMonths(2),
        ]);

        $response = $this->actingAs($user)
            ->get(route('planning', [
                'tab'             => 'retirement',
                'birth_year'      => now()->year - 40,
                'retirement_age'  => 65,
                'current_value'   => 100000,
                'monthly_contrib' => 1000,
                'annual_return'   => 7,
                'annual_expenses' => 40000,
            ]))
            ->assertOk();

        $result = $response->viewData('result');

        $this->assertSame(25, $result['years_left']);
        $this->assertSame(1000000.0, $result['target']);
        // 100k at 7% plus $1k/mo over 25 years clears the $1M target (~1.38M)
        $this->assertGreaterThan($result['target'], $result['projected_fv']);
        $this->assertLessThan(0, $result['gap']);
        $this->assertNotEmpty($result['benchmarks']);
        $this->assertTrue($result['on_track']);
    }

    public function test_retirement_tab_has_no_result_when_already_past_retirement_age(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('planning', [
                'tab'            => 'retirement',
                'birth_year'     => now()->year - 70,
                'retirement_age' => 65,
            ]))
            ->assertOk();

        $this->assertNull($response->viewData('result'));
    }
}

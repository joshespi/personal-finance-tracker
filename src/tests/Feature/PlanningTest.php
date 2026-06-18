<?php

namespace Tests\Feature;

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
}

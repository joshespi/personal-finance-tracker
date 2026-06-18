<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AnalysisTest extends TestCase
{
    public function test_requires_auth(): void
    {
        $this->get(route('analysis'))->assertRedirect(route('login'));
    }

    public function test_default_tab_renders_cashflow(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('analysis'))
            ->assertOk();
    }

    public function test_trends_tab_renders(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('analysis', ['tab' => 'trends']))
            ->assertOk();
    }

    public function test_budget_rule_tab_renders(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('analysis', ['tab' => 'budget-rule']))
            ->assertOk();
    }
}

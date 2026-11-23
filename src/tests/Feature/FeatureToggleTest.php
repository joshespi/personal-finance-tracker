<?php

namespace Tests\Feature;

use App\Enums\Feature;
use App\Models\User;
use Tests\TestCase;

class FeatureToggleTest extends TestCase
{
    private function userWith(array $on): User
    {
        $features = [];
        foreach (Feature::cases() as $f) {
            $features[$f->value] = in_array($f, $on, true);
        }

        return User::factory()->create(['features' => $features]);
    }

    public function test_everything_is_on_by_default(): void
    {
        $user = User::factory()->create();

        foreach (Feature::cases() as $f) {
            $this->assertTrue($user->hasFeature($f));
        }
        $this->assertCount(3, $user->enabledFeatures());
    }

    public function test_a_feature_missing_from_the_stored_map_counts_as_on(): void
    {
        $user = User::factory()->create(['features' => ['investing' => false]]);

        $this->assertFalse($user->hasFeature(Feature::Investing));
        $this->assertTrue($user->hasFeature(Feature::Cash));
    }

    public function test_quick_add_needs_cash_or_investing(): void
    {
        $this->assertTrue($this->userWith([Feature::Cash])->canQuickAdd());
        $this->assertTrue($this->userWith([Feature::Investing])->canQuickAdd());
        $this->assertFalse($this->userWith([Feature::Retirement])->canQuickAdd());
    }

    public function test_investing_routes_404_when_off_and_work_when_on(): void
    {
        $off = $this->userWith([Feature::Cash, Feature::Retirement]);
        $this->actingAs($off)->get(route('portfolios.index'))->assertNotFound();
        $this->actingAs($off)->get(route('tax.summary'))->assertNotFound();
        $this->actingAs($off)->get(route('transactions.all'))->assertNotFound();

        $on = $this->userWith([Feature::Investing]);
        $this->actingAs($on)->get(route('portfolios.index'))->assertOk();
    }

    public function test_cash_routes_404_when_off(): void
    {
        $off = $this->userWith([Feature::Investing]);

        $this->actingAs($off)->get(route('envelopes.index'))->assertNotFound();
        $this->actingAs($off)->get(route('cash-accounts.index'))->assertNotFound();
        $this->actingAs($off)->get(route('liabilities.index'))->assertNotFound();
        $this->actingAs($off)->get(route('analysis'))->assertNotFound();
    }

    public function test_pensions_follow_retirement(): void
    {
        $this->actingAs($this->userWith([Feature::Investing, Feature::Cash]))
            ->get(route('pensions.index'))->assertNotFound();
        $this->actingAs($this->userWith([Feature::Retirement]))
            ->get(route('pensions.index'))->assertOk();
    }

    public function test_planning_tabs_follow_features(): void
    {
        $retirementOnly = $this->userWith([Feature::Retirement]);

        // The default tab falls to the first allowed one instead of the hidden debt payoff tab.
        $this->actingAs($retirementOnly)->get(route('planning'))
            ->assertOk()->assertViewHas('tab', 'retirement');
        $this->actingAs($retirementOnly)->get(route('planning', ['tab' => 'debt-payoff']))->assertNotFound();
        $this->actingAs($retirementOnly)->get(route('planning', ['tab' => 'allocator']))->assertNotFound();
        $this->actingAs($retirementOnly)->postJson(route('planning.debt-payoff.simulate'), ['extra_payment' => 10])
            ->assertNotFound();

        $cashOnly = $this->userWith([Feature::Cash]);
        $this->actingAs($cashOnly)->get(route('planning', ['tab' => 'retirement']))->assertNotFound();
        $this->actingAs($cashOnly)->get(route('planning'))->assertOk()->assertViewHas('tab', 'debt-payoff');
    }

    public function test_sidebar_hides_disabled_modules(): void
    {
        $user = $this->userWith([Feature::Cash]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Envelopes')
            ->assertDontSee('Dividends')
            ->assertDontSee('Log Buy');
    }

    public function test_dashboard_renders_with_each_single_feature(): void
    {
        foreach (Feature::cases() as $f) {
            $this->actingAs($this->userWith([$f]))->get(route('dashboard'))->assertOk();
        }
    }

    public function test_dashboard_ignores_cash_when_cash_is_off(): void
    {
        $this->actingAs($this->userWith([Feature::Investing]))
            ->get(route('dashboard'))
            ->assertViewHas('totalCash', 0.0)
            ->assertViewHas('readyToAssign', 0.0);
    }

    public function test_widget_of_disabled_module_is_hidden_but_preference_survives(): void
    {
        $user = $this->userWith([Feature::Cash]);
        $user->update(['dashboard_preferences' => ['tile_portfolio_value' => true, 'tile_cash' => true]]);

        $this->assertFalse($user->showsWidget('tile_portfolio_value'));
        $this->assertTrue($user->showsWidget('tile_cash'));

        // Saving the display form (which can't submit the hidden widget) keeps its stored preference.
        $this->actingAs($user)->patch(route('profile.display'), ['widgets' => ['tile_cash']])->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->dashboard_preferences['tile_portfolio_value']);
    }

    public function test_profile_can_update_features(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.features'), ['features' => ['cash', 'retirement']])
            ->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertFalse($user->hasFeature(Feature::Investing));
        $this->assertTrue($user->hasFeature(Feature::Cash));
        $this->assertTrue($user->hasFeature(Feature::Retirement));
    }

    public function test_cannot_turn_every_feature_off_or_submit_unknown_ones(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.features'), [])->assertSessionHasErrors('features');
        $this->actingAs($user)->patch(route('profile.features'), ['features' => ['bogus']])->assertSessionHasErrors('features.0');
        $this->assertCount(3, $user->fresh()->enabledFeatures());
    }

    public function test_profile_page_renders_features_section(): void
    {
        $this->actingAs($this->userWith([Feature::Cash]))
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Save Features');
    }

    public function test_email_summary_sections_of_disabled_modules_are_not_wanted(): void
    {
        $user = $this->userWith([Feature::Cash]);
        $user->update(['email_summary_sections' => ['investing', 'budgeting', 'net_worth']]);

        $this->assertFalse($user->wantsEmailSummarySection('investing'));
        $this->assertTrue($user->wantsEmailSummarySection('budgeting'));
        $this->assertTrue($user->wantsEmailSummarySection('net_worth'));
    }
}

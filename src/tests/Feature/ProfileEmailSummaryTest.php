<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ProfileEmailSummaryTest extends TestCase
{
    public function test_user_can_save_frequencies_and_sections(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.email-summary'), [
                'frequencies' => ['weekly', 'monthly'],
                'sections'    => ['budgeting', 'net_worth'],
            ])
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertEquals(['weekly', 'monthly'], $user->email_summary_frequencies);
        $this->assertEquals(['budgeting', 'net_worth'], $user->email_summary_sections);
    }

    public function test_omitting_arrays_clears_preferences(): void
    {
        $user = User::factory()->create([
            'email_summary_frequencies' => ['daily'],
            'email_summary_sections'    => ['budgeting'],
        ]);

        $this->actingAs($user)
            ->patch(route('profile.email-summary'), [])
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertEquals([], $user->email_summary_frequencies);
        $this->assertEquals([], $user->email_summary_sections);
    }

    public function test_invalid_frequency_value_fails_validation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.email-summary'), ['frequencies' => ['yearly']])
            ->assertSessionHasErrors('frequencies.0');
    }

    public function test_invalid_section_value_fails_validation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.email-summary'), ['sections' => ['not_a_real_section']])
            ->assertSessionHasErrors('sections.0');
    }

    public function test_malformed_input_fails_validation_instead_of_crashing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.email-summary'), ['frequencies' => [['nested']]])
            ->assertSessionHasErrors('frequencies.0');
    }
}

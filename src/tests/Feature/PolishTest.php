<?php

namespace Tests\Feature;

use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Tests\TestCase;

class PolishTest extends TestCase
{
    public function test_page_title_comes_from_the_header_heading(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('envelopes.index'))
            ->assertOk()
            ->assertSee('<title>Budget Envelopes · '.config('app.name').'</title>', false);
    }

    public function test_unknown_page_renders_the_custom_404(): void
    {
        $this->get('/no-such-page-here')
            ->assertNotFound()
            ->assertSee('Page not found')
            ->assertSee('Back to dashboard');
    }

    public function test_edit_transaction_shows_the_refusal_flash(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->create(['user_id' => $user->id]);
        $tx        = Transaction::factory()->create(['portfolio_id' => $portfolio->id]);

        $this->actingAs($user)
            ->withSession(['error' => 'That portfolio is closed. Reopen it to move transactions into it.'])
            ->get(route('transactions.edit', $tx))
            ->assertOk()
            ->assertSee('That portfolio is closed. Reopen it to move transactions into it.');
    }

    public function test_portfolio_show_still_displays_the_error_flash(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->withSession(['error' => 'This portfolio is closed. Reopen it to add transactions.'])
            ->get(route('portfolios.show', $portfolio))
            ->assertOk()
            ->assertSee('This portfolio is closed. Reopen it to add transactions.');
    }
}

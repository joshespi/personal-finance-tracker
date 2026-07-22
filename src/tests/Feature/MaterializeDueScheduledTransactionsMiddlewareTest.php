<?php

namespace Tests\Feature;

use App\Models\CashTransaction;
use App\Models\ScheduledTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterializeDueScheduledTransactionsMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_visiting_any_authenticated_page_materializes_due_transactions(): void
    {
        // Regression: materialization used to run only from AllTransactions::mount() —
        // a due schedule was invisible until that specific page was visited. The
        // dashboard neither calls ScheduledTransactionService directly nor renders
        // scheduled transactions itself, so this only passes via the web-group middleware.
        $user = User::factory()->create();

        $scheduled = ScheduledTransaction::factory()->for($user)->create([
            'type'        => 'cash_deposit',
            'amount'      => 250,
            'next_due_at' => today(),
            'recurrence'  => 'monthly',
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $this->assertDatabaseHas('cash_transactions', [
            'cash_account_id' => $scheduled->cash_account_id,
            'type'            => 'deposit',
            'amount'          => 250,
        ]);
        $this->assertEquals(1, CashTransaction::count());

        $scheduled->refresh();
        $this->assertTrue($scheduled->next_due_at->gt(today()));
    }

    public function test_does_not_materialize_for_guests(): void
    {
        $user = User::factory()->create();
        ScheduledTransaction::factory()->for($user)->create(['next_due_at' => today()]);

        $this->get(route('login'))->assertOk();

        $this->assertEquals(0, CashTransaction::count());
    }

    public function test_does_not_materialize_another_users_schedule(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        ScheduledTransaction::factory()->for($other)->create(['next_due_at' => today()]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $this->assertEquals(0, CashTransaction::count());
    }
}

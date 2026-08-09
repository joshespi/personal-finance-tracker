<?php

namespace Tests\Feature;

use App\Enums\EmailSummarySection;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Envelope;
use App\Models\Portfolio;
use App\Models\ScheduledTransaction;
use App\Models\User;
use App\Services\EmailSummaryService;
use Tests\TestCase;

class EmailSummaryServiceTest extends TestCase
{
    public function test_budgeting_section_totals_deposits_and_withdrawals_in_window(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();

        CashTransaction::factory()->for($account)->deposit()->create(['amount' => 300, 'occurred_at' => now()]);
        CashTransaction::factory()->for($account)->withdrawal()->create(['amount' => 120, 'occurred_at' => now()]);
        // Outside the window — must not be counted.
        CashTransaction::factory()->for($account)->deposit()->create(['amount' => 9999, 'occurred_at' => now()->subMonths(3)]);

        $data = app(EmailSummaryService::class)->compute(
            $user,
            now()->subWeek(),
            now(),
            collect([EmailSummarySection::Budgeting])
        );

        $this->assertEquals(300.0, $data['budgeting']['deposits']);
        $this->assertEquals(120.0, $data['budgeting']['withdrawals']);
        $this->assertEquals(180.0, $data['budgeting']['net']);
        $this->assertEquals(2, $data['budgeting']['transactionCount']);
    }

    public function test_upcoming_scheduled_section_includes_due_transactions_within_horizon(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();

        ScheduledTransaction::factory()->for($user)->for($account, 'cashAccount')->create([
            'next_due_at' => now()->addDays(3),
            'is_active'   => true,
        ]);
        ScheduledTransaction::factory()->for($user)->for($account, 'cashAccount')->create([
            'next_due_at' => now()->addMonths(2),
            'is_active'   => true,
        ]);

        $data = app(EmailSummaryService::class)->compute(
            $user,
            now()->subWeek(),
            now(),
            collect([EmailSummarySection::UpcomingScheduled])
        );

        $this->assertCount(1, $data['upcoming_scheduled']);
    }

    public function test_category_changes_section_computes_percent_change_per_envelope(): void
    {
        $user     = User::factory()->create();
        $account  = CashAccount::factory()->for($user)->create();
        $envelope = Envelope::factory()->for($user)->create(['name' => 'Groceries']);

        // Current window is [since, until] = [-7d, 0d]; the prior window of equal
        // length is [-14d, -7d]. One spend lands in each.
        CashTransaction::factory()->for($account)->spend($envelope)->create([
            'amount'      => 100,
            'occurred_at' => now()->subDays(10),
        ]);
        CashTransaction::factory()->for($account)->spend($envelope)->create([
            'amount'      => 150,
            'occurred_at' => now()->subDays(3),
        ]);

        $data = app(EmailSummaryService::class)->compute(
            $user,
            now()->subDays(7),
            now(),
            collect([EmailSummarySection::CategoryChanges])
        );

        $row = $data['category_changes']->firstWhere('envelope', 'Groceries');
        $this->assertNotNull($row);
        $this->assertEquals(150.0, $row['current']);
        $this->assertEquals(100.0, $row['previous']);
        $this->assertEquals(50.0, $row['percentChange']);
    }

    public function test_warnings_section_flags_over_budget_envelope_and_negative_balance(): void
    {
        $user     = User::factory()->create();
        $account  = CashAccount::factory()->for($user)->create();
        $envelope = Envelope::factory()->for($user)->create(['name' => 'Dining', 'monthly_target' => 100]);

        CashTransaction::factory()->for($account)->spend($envelope)->create([
            'amount'      => 150,
            'occurred_at' => now(),
        ]);
        CashTransaction::factory()->for($account)->withdrawal()->create([
            'amount'      => 500,
            'occurred_at' => now(),
        ]);

        $data = app(EmailSummaryService::class)->compute(
            $user,
            now()->subWeek(),
            now(),
            collect([EmailSummarySection::Warnings])
        );

        $this->assertTrue($data['warnings']['overBudgetEnvelopes']->contains('envelope', 'Dining'));
        $this->assertTrue($data['warnings']['lowBalanceAccounts']->isNotEmpty());
    }

    /**
     * Regression: a credit-card CashAccount owing money has a negative working balance
     * by design (see User::creditCardDebts()) — that used to trip the same `balance < 0`
     * filter as a checking account overdraft, so every summary email would warn about a
     * card simply carrying a balance. Credit cards must be excluded from this section.
     */
    public function test_warnings_section_excludes_credit_card_from_low_balance(): void
    {
        $user = User::factory()->create();
        $card = CashAccount::factory()->for($user)->create(['account_type' => 'credit_card']);

        CashTransaction::factory()->for($card)->withdrawal()->create(['amount' => 500, 'occurred_at' => now()]);

        $data = app(EmailSummaryService::class)->compute(
            $user,
            now()->subWeek(),
            now(),
            collect([EmailSummarySection::Warnings])
        );

        $this->assertTrue($data['warnings']['lowBalanceAccounts']->isEmpty());
    }

    public function test_only_requested_sections_are_present_in_output(): void
    {
        $user = User::factory()->create();

        $data = app(EmailSummaryService::class)->compute(
            $user,
            now()->subWeek(),
            now(),
            collect([EmailSummarySection::Budgeting])
        );

        $this->assertArrayHasKey('budgeting', $data);
        $this->assertArrayNotHasKey('investing', $data);
        $this->assertArrayNotHasKey('net_worth', $data);
    }

    public function test_investing_section_returns_null_value_change_without_snapshots(): void
    {
        $user = User::factory()->create();
        Portfolio::factory()->for($user)->create();

        $data = app(EmailSummaryService::class)->compute(
            $user,
            now()->subWeek(),
            now(),
            collect([EmailSummarySection::Investing])
        );

        $this->assertNull($data['investing']['valueChange']);
    }
}

<?php

namespace Tests\Feature;

use App\Livewire\AllTransactions;
use App\Livewire\TransactionList;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Envelope;
use App\Models\IncomeCategory;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Column sorting on the two cash ledgers. Both get it from
 * ManagesCashTransactionForm, so the shared behaviour is exercised on the
 * single-account list and the account-name/cross-account cases on the aggregate one.
 */
class CashLedgerSortingTest extends TestCase
{
    /** Three descriptions, deliberately in neither date nor alphabetical order. */
    private function seedAccount(User $user): CashAccount
    {
        $account = CashAccount::factory()->for($user)->create(['name' => 'Checking']);

        CashTransaction::factory()->for($account, 'cashAccount')->withdrawal()->create([
            'description' => 'Bravo rent', 'amount' => 900, 'occurred_at' => '2026-03-01',
        ]);
        CashTransaction::factory()->for($account, 'cashAccount')->deposit()->create([
            'description' => 'Alpha paycheck', 'amount' => 2500, 'occurred_at' => '2026-01-15',
        ]);
        CashTransaction::factory()->for($account, 'cashAccount')->withdrawal()->create([
            'description' => 'Charlie coffee', 'amount' => 12, 'occurred_at' => '2026-02-10',
        ]);

        return $account;
    }

    public function test_default_sort_is_newest_first(): void
    {
        $user    = User::factory()->create();
        $account = $this->seedAccount($user);

        Livewire::actingAs($user)
            ->test(TransactionList::class, ['account' => $account])
            ->assertSet('sortField', 'occurred_at')
            ->assertSet('sortDirection', 'desc')
            ->assertSeeInOrder(['Bravo rent', 'Charlie coffee', 'Alpha paycheck']);
    }

    public function test_headers_render_as_sort_buttons_with_a_direction_arrow(): void
    {
        $user    = User::factory()->create();
        $account = $this->seedAccount($user);

        Livewire::actingAs($user)
            ->test(TransactionList::class, ['account' => $account])
            // Guards the x-sort-th tag actually compiling — a malformed component tag
            // is passed through as literal text rather than raising an error.
            ->assertDontSee('<x-sort-th', false)
            ->assertSee('wire:click="sortBy(\'description\')"', false)
            ->assertSee('aria-sort="descending"', false)
            ->assertSee('▼', false);
    }

    public function test_sorting_by_date_ascending_reverses_the_ledger(): void
    {
        $user    = User::factory()->create();
        $account = $this->seedAccount($user);

        Livewire::actingAs($user)
            ->test(TransactionList::class, ['account' => $account])
            ->call('sortBy', 'occurred_at')
            ->assertSet('sortDirection', 'asc')
            ->assertSeeInOrder(['Alpha paycheck', 'Charlie coffee', 'Bravo rent']);
    }

    public function test_clicking_the_same_column_twice_flips_direction(): void
    {
        $user    = User::factory()->create();
        $account = $this->seedAccount($user);

        Livewire::actingAs($user)
            ->test(TransactionList::class, ['account' => $account])
            ->call('sortBy', 'description')
            ->assertSet('sortDirection', 'asc')
            ->assertSeeInOrder(['Alpha paycheck', 'Bravo rent', 'Charlie coffee'])
            ->call('sortBy', 'description')
            ->assertSet('sortDirection', 'desc')
            ->assertSeeInOrder(['Charlie coffee', 'Bravo rent', 'Alpha paycheck']);
    }

    public function test_outflow_sort_puts_the_biggest_spend_first_and_deposits_last(): void
    {
        $user    = User::factory()->create();
        $account = $this->seedAccount($user);

        Livewire::actingAs($user)
            ->test(TransactionList::class, ['account' => $account])
            ->call('sortBy', 'outflow')
            ->assertSet('sortDirection', 'desc')
            ->assertSeeInOrder(['Bravo rent', 'Charlie coffee', 'Alpha paycheck']);
    }

    public function test_inflow_sort_puts_the_biggest_deposit_first(): void
    {
        $user    = User::factory()->create();
        $account = $this->seedAccount($user);

        // The two withdrawals both count as 0 inflow, so they fall to the bottom in
        // the id tie-break order (newest-entered first, matching the desc direction).
        Livewire::actingAs($user)
            ->test(TransactionList::class, ['account' => $account])
            ->call('sortBy', 'inflow')
            ->assertSeeInOrder(['Alpha paycheck', 'Charlie coffee', 'Bravo rent']);
    }

    public function test_status_sort_groups_cleared_and_pending(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();

        CashTransaction::factory()->for($account, 'cashAccount')->deposit()->pending()->create([
            'description' => 'Still pending', 'occurred_at' => '2026-01-01',
        ]);
        CashTransaction::factory()->for($account, 'cashAccount')->deposit()->cleared()->create([
            'description' => 'Already cleared', 'occurred_at' => '2026-01-02',
        ]);

        Livewire::actingAs($user)
            ->test(TransactionList::class, ['account' => $account])
            ->call('sortBy', 'cleared')
            ->assertSeeInOrder(['Already cleared', 'Still pending']);
    }

    public function test_type_sort_orders_deposits_before_withdrawals(): void
    {
        $user    = User::factory()->create();
        $account = $this->seedAccount($user);

        Livewire::actingAs($user)
            ->test(TransactionList::class, ['account' => $account])
            ->call('sortBy', 'type')
            ->assertSet('sortDirection', 'asc')
            ->assertSeeInOrder(['Alpha paycheck', 'Bravo rent']);
    }

    public function test_category_sort_spans_envelopes_and_income_categories(): void
    {
        $user     = User::factory()->create();
        $account  = CashAccount::factory()->for($user)->create();
        $envelope = Envelope::factory()->for($user)->create(['name' => 'Zoo Trips']);
        $category = IncomeCategory::factory()->for($user)->create(['name' => 'Salary']);

        CashTransaction::factory()->for($account, 'cashAccount')->withdrawal()->create([
            'description' => 'Zoo membership', 'envelope_id' => $envelope->id, 'occurred_at' => '2026-01-01',
        ]);
        CashTransaction::factory()->for($account, 'cashAccount')->deposit()->create([
            'description' => 'Monthly pay', 'income_category_id' => $category->id, 'occurred_at' => '2026-01-02',
        ]);

        // 'Salary' < 'Zoo Trips' alphabetically, so the income row leads even though
        // it is the newer transaction.
        Livewire::actingAs($user)
            ->test(TransactionList::class, ['account' => $account])
            ->call('sortBy', 'category')
            ->assertSeeInOrder(['Monthly pay', 'Zoo membership']);
    }

    public function test_aggregate_ledger_sorts_by_account_name(): void
    {
        $user     = User::factory()->create();
        $checking = CashAccount::factory()->for($user)->create(['name' => 'Zulu Checking']);
        $savings  = CashAccount::factory()->for($user)->create(['name' => 'Alpha Savings']);

        CashTransaction::factory()->for($checking, 'cashAccount')->deposit()->create([
            'description' => 'From Zulu', 'occurred_at' => '2026-02-01',
        ]);
        CashTransaction::factory()->for($savings, 'cashAccount')->deposit()->create([
            'description' => 'From Alpha', 'occurred_at' => '2026-01-01',
        ]);

        Livewire::actingAs($user)
            ->test(AllTransactions::class)
            ->call('sortBy', 'account')
            ->assertSet('sortDirection', 'asc')
            ->assertSeeInOrder(['From Alpha', 'From Zulu']);
    }

    public function test_sorting_resets_pagination_to_page_one(): void
    {
        $user    = User::factory()->create();
        $account = $this->seedAccount($user);

        Livewire::actingAs($user)
            ->test(TransactionList::class, ['account' => $account])
            ->set('paginators.page', 2)
            ->call('sortBy', 'description')
            ->assertSet('paginators.page', 1);
    }

    public function test_unknown_sort_field_is_ignored(): void
    {
        $user    = User::factory()->create();
        $account = $this->seedAccount($user);

        Livewire::actingAs($user)
            ->test(TransactionList::class, ['account' => $account])
            ->call('sortBy', 'amount); drop table cash_transactions; --')
            ->assertSet('sortField', 'occurred_at')
            ->assertSet('sortDirection', 'desc')
            ->assertSeeInOrder(['Bravo rent', 'Charlie coffee', 'Alpha paycheck']);
    }

    public function test_client_tampered_sort_state_falls_back_to_the_default_column(): void
    {
        $user    = User::factory()->create();
        $account = $this->seedAccount($user);

        // sortField/sortDirection are public, so a client can set them directly —
        // applySort() must whitelist rather than trust them.
        Livewire::actingAs($user)
            ->test(TransactionList::class, ['account' => $account])
            ->set('sortField', '(select 1)')
            ->set('sortDirection', 'desc; drop table cash_transactions')
            ->assertSeeInOrder(['Bravo rent', 'Charlie coffee', 'Alpha paycheck']);
    }

    public function test_sort_survives_a_search_filter(): void
    {
        $user    = User::factory()->create();
        $account = $this->seedAccount($user);

        Livewire::actingAs($user)
            ->test(TransactionList::class, ['account' => $account])
            ->call('sortBy', 'description')
            ->set('filter', 'a')
            ->assertSet('sortField', 'description')
            ->assertSeeInOrder(['Alpha paycheck', 'Bravo rent', 'Charlie coffee']);
    }
}

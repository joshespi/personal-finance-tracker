<?php

namespace App\Livewire;

use App\Concerns\ManagesCashTransactionForm;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Consolidated ledger across every cash account the user owns — the "All Accounts"
 * view. Shares its add/edit transaction form and CRUD with the single-account
 * TransactionList via ManagesCashTransactionForm; this class adds the account
 * column/picker and aggregates balances across all accounts.
 */
class AllTransactions extends Component
{
    use ManagesCashTransactionForm, WithPagination;

    public ?int $newAccountId = null;

    public ?int $editAccountId = null;

    /** Narrow the ledger to a single account; 0/empty means all accounts. */
    public ?int $accountFilter = null;

    /** Narrow by clearing status: '' = all, 'pending' = uncleared, 'cleared'. */
    public string $statusFilter = '';

    public function mount(): void
    {
        // Due recurring transactions are materialized app-wide by
        // MaterializeDueScheduledTransactions middleware before this mounts.
        $this->newOccurredAt = now()->format('Y-m-d');
        $this->newAccountId  = $this->accounts->first()?->id;
    }

    /** Ids of the accounts this ledger spans — all of the user's, by default. */
    private function accountIds(): Collection
    {
        return $this->accounts->pluck('id');
    }

    public function getAccountsProperty()
    {
        return auth()->user()->cashAccounts()->orderBy('name')->get(['id', 'name', 'account_type', 'currency']);
    }

    public function getTransactionsProperty()
    {
        return $this->filteredQuery()
            ->with([
                'cashAccount:id,name', 'envelope:id,name', 'incomeCategory:id,name,color',
                'linkedFrom:id,cash_account_id', 'linkedFrom.cashAccount:id,name',
                'linkedTo:id,cash_account_id,linked_transaction_id', 'linkedTo.cashAccount:id,name',
            ])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);
    }

    /** All cash transactions, narrowed by the account filter and the search filter. */
    private function filteredQuery()
    {
        // whereIn over the user's own account ids is the ownership guard; the account
        // filter, when set, narrows within that set (a non-owned id can't slip through).
        $query = CashTransaction::whereIn('cash_account_id', $this->accountIds())
            ->when($this->accountFilter, fn ($q) => $q->where('cash_account_id', $this->accountFilter))
            ->when($this->statusFilter === 'pending', fn ($q) => $q->where('cleared', false))
            ->when($this->statusFilter === 'cleared', fn ($q) => $q->where('cleared', true));

        return $this->applySearchFilter($query);
    }

    /** Aggregate working/cleared/pending balances across every account, in one query. */
    public function getBalancesProperty(): array
    {
        return CashTransaction::balanceTotals(
            CashTransaction::whereIn('cash_account_id', $this->accountIds())
        );
    }

    public function getScheduledProperty()
    {
        return auth()->user()->scheduledTransactions()
            ->where('is_active', true)
            ->with(['envelope:id,name,color', 'cashAccount:id,name'])
            ->orderByDesc('next_due_at')
            // Within the same day, list outgoing first and inflows below them, since
            // inflows are the ones that get "entered" first to fund the day's spend.
            ->inflowsLast()
            ->get();
    }

    public function updatedAccountFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    protected function writeAccountRules(string $prefix): array
    {
        return ["{$prefix}AccountId" => ['required', 'integer']];
    }

    protected function resolveWriteAccount(array $data, string $prefix): CashAccount
    {
        $account = $this->ownedAccount($data["{$prefix}AccountId"]);
        Gate::authorize('update', $account);

        return $account;
    }

    protected function afterStartEdit(CashTransaction $transaction): void
    {
        $this->editAccountId = $transaction->cash_account_id;
    }

    protected function ownedTransaction(?int $id): CashTransaction
    {
        $t = CashTransaction::whereIn('cash_account_id', $this->accountIds())->find($id);
        abort_unless($t, 404);

        return $t;
    }

    /** Fetch one of the user's cash accounts or 403. */
    private function ownedAccount(int $id): CashAccount
    {
        $account = CashAccount::find($id);
        abort_unless($account && $account->user_id === auth()->id(), 403);

        return $account;
    }

    public function render()
    {
        return view('livewire.all-transactions');
    }
}

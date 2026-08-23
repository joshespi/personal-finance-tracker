<?php

namespace App\Livewire;

use App\Concerns\ManagesCashTransactionForm;
use App\Concerns\SortsCashLedger;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class TransactionList extends Component
{
    use ManagesCashTransactionForm, SortsCashLedger, WithPagination;

    public CashAccount $account;

    public function mount(CashAccount $account): void
    {
        abort_unless($account->user_id === auth()->id(), 403);
        $this->account       = $account;
        $this->newOccurredAt = now()->format('Y-m-d');
    }

    public function getTransactionsProperty()
    {
        $query = $this->filteredQuery()
            ->with([
                'envelope:id,name', 'incomeCategory:id,name,color',
                'linkedFrom:id,cash_account_id', 'linkedFrom.cashAccount:id,name,account_type',
                'linkedTo:id,cash_account_id,linked_transaction_id', 'linkedTo.cashAccount:id,name,account_type',
            ]);

        $paginated = $this->applySort($query)->paginate(self::PER_PAGE);

        // Every row belongs to $this->account (see filteredQuery()) — attach it directly
        // instead of eager-loading a relation whose target row this component already holds.
        $paginated->getCollection()->each(fn (CashTransaction $t) => $t->setRelation('cashAccount', $this->account));

        return $paginated;
    }

    /** Account transactions narrowed by the current filter (amount match or description search). */
    private function filteredQuery()
    {
        return $this->applySearchFilter($this->account->transactions());
    }

    /** Working/cleared/pending balances (one query) — independent of the filter and pagination. */
    public function getBalancesProperty(): array
    {
        return $this->account->balances();
    }

    public function getScheduledProperty()
    {
        return $this->account->scheduledTransactions()
            ->where('is_active', true)
            ->with('envelope:id,name,color')
            ->orderBy('next_due_at')
            ->get();
    }

    protected function writeAccountRules(string $prefix): array
    {
        return [];
    }

    protected function resolveWriteAccount(array $data, string $prefix): CashAccount
    {
        Gate::authorize('update', $this->account);

        return $this->account;
    }

    protected function ownedTransaction(?int $id): CashTransaction
    {
        $t = $this->account->transactions()->find($id);
        abort_unless($t, 404);

        return $t;
    }

    public function render()
    {
        return view('livewire.transaction-list');
    }
}

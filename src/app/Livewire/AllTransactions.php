<?php

namespace App\Livewire;

use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Envelope;
use App\Models\IncomeCategory;
use App\Services\ScheduledTransactionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Consolidated ledger across every cash account the user owns — the "All Accounts"
 * view. Mirrors the single-account TransactionList but adds an account column/picker
 * and aggregates balances across all accounts.
 */
class AllTransactions extends Component
{
    use WithPagination;

    public string $newType = 'deposit';

    public string $newAmount = '';

    public string $newDescription = '';

    public string $newOccurredAt = '';

    public ?int $newAccountId = null;

    public ?int $newEnvelopeId = null;

    public ?int $newIncomeCategoryId = null;

    public bool $newCleared = false;

    public ?int $editingId = null;

    public string $editType = 'deposit';

    public string $editAmount = '';

    public string $editDescription = '';

    public string $editOccurredAt = '';

    public ?int $editAccountId = null;

    public ?int $editEnvelopeId = null;

    public ?int $editIncomeCategoryId = null;

    public bool $editCleared = false;

    public string $filter = '';

    /** Narrow the ledger to a single account; 0/empty means all accounts. */
    public ?int $accountFilter = null;

    /** Rows per page. */
    public const PER_PAGE = 50;

    public function mount(ScheduledTransactionService $service): void
    {
        // Materialize any due recurring transactions so the ledger reflects them.
        $service->materializeForUser(auth()->user());

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
            ->with(['cashAccount:id,name', 'envelope:id,name', 'incomeCategory:id,name,color'])
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
            ->when($this->accountFilter, fn ($q) => $q->where('cash_account_id', $this->accountFilter));

        $f = strtolower(trim($this->filter));

        if ($f !== '') {
            $asNum = is_numeric($f) ? (float) $f : null;
            if ($asNum !== null && $asNum > 0) {
                $query->whereRaw('ABS(amount - ?) < 0.005', [$asNum]);
            } else {
                $query->whereRaw('LOWER(description) LIKE ?', ['%'.$f.'%']);
            }
        }

        return $query;
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
            // Within the same day, list outgoing first and income below it, since
            // income is the one that gets "entered" first to fund the day's spend.
            ->orderByRaw("CASE WHEN type = 'cash_deposit' THEN 1 ELSE 0 END")
            ->get();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function updatedAccountFilter(): void
    {
        $this->resetPage();
    }

    public function getEnvelopesProperty()
    {
        return auth()->user()->envelopes()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
    }

    public function getIncomeCategoriesProperty()
    {
        return auth()->user()->incomeCategories()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
    }

    public function addTransaction(): void
    {
        $data = $this->validate([
            'newAccountId'        => ['required', 'integer'],
            'newType'             => ['required', 'in:deposit,withdrawal'],
            'newAmount'           => ['required', 'numeric', 'gt:0'],
            'newDescription'      => ['nullable', 'string', 'max:500'],
            'newOccurredAt'       => ['required', 'date'],
            'newEnvelopeId'       => ['nullable', 'integer', 'exists:envelopes,id'],
            'newIncomeCategoryId' => [
                'nullable', 'integer',
                IncomeCategory::ownershipRule(auth()->id()),
            ],
            'newCleared' => ['boolean'],
        ]);

        $account = $this->ownedAccount($data['newAccountId']);
        Gate::authorize('update', $account);

        $envelopeId = null;
        if ($data['newEnvelopeId'] && $data['newType'] === 'withdrawal') {
            $env = Envelope::find($data['newEnvelopeId']);
            abort_unless($env && $env->user_id === auth()->id(), 403);
            $envelopeId = $data['newEnvelopeId'];
        }

        $account->transactions()->create([
            'type'               => $data['newType'],
            'amount'             => $data['newAmount'],
            'description'        => $data['newDescription'] ?: null,
            'occurred_at'        => $data['newOccurredAt'],
            'envelope_id'        => $envelopeId,
            'income_category_id' => $this->resolveIncomeCategoryId($data['newIncomeCategoryId'], $data['newType']),
            'cleared'            => $this->newCleared,
        ]);

        $this->reset(['newAmount', 'newDescription', 'newEnvelopeId', 'newIncomeCategoryId', 'newCleared']);
        $this->newOccurredAt = now()->format('Y-m-d');
        $this->resetPage();
    }

    public function startEdit(int $id): void
    {
        $t = $this->ownedTransaction($id);

        $this->editingId            = $id;
        $this->editAccountId        = $t->cash_account_id;
        $this->editType             = $t->type;
        $this->editAmount           = (string) $t->amount;
        $this->editDescription      = $t->description ?? '';
        $this->editOccurredAt       = $t->occurred_at->format('Y-m-d');
        $this->editEnvelopeId       = $t->envelope_id;
        $this->editIncomeCategoryId = $t->income_category_id;
        $this->editCleared          = (bool) $t->cleared;

        $this->resetErrorBag();
    }

    public function saveEdit(): void
    {
        $t = $this->ownedTransaction($this->editingId);
        Gate::authorize('update', $t);

        $data = $this->validate([
            'editAccountId'        => ['required', 'integer'],
            'editType'             => ['required', 'in:deposit,withdrawal'],
            'editAmount'           => ['required', 'numeric', 'gt:0'],
            'editDescription'      => ['nullable', 'string', 'max:500'],
            'editOccurredAt'       => ['required', 'date'],
            'editEnvelopeId'       => ['nullable', 'integer', 'exists:envelopes,id'],
            'editIncomeCategoryId' => [
                'nullable', 'integer',
                IncomeCategory::ownershipRule(auth()->id()),
            ],
            'editCleared' => ['boolean'],
        ]);

        // Moving the transaction to another account is allowed, but only to one you own.
        $account = $this->ownedAccount($data['editAccountId']);
        Gate::authorize('update', $account);

        $envelopeId = null;
        if ($data['editEnvelopeId'] && $data['editType'] === 'withdrawal') {
            $env = Envelope::find($data['editEnvelopeId']);
            abort_unless($env && $env->user_id === auth()->id(), 403);
            $envelopeId = $data['editEnvelopeId'];
        }

        $t->update([
            'cash_account_id'    => $account->id,
            'type'               => $data['editType'],
            'amount'             => $data['editAmount'],
            'description'        => $data['editDescription'] ?: null,
            'occurred_at'        => $data['editOccurredAt'],
            'envelope_id'        => $envelopeId,
            'income_category_id' => $this->resolveIncomeCategoryId($data['editIncomeCategoryId'], $data['editType']),
            'cleared'            => $this->editCleared,
        ]);

        $this->editingId = null;
    }

    /** A category only applies to deposits; ownership is enforced by validation. */
    private function resolveIncomeCategoryId(?int $categoryId, string $type): ?int
    {
        return $type === 'deposit' ? $categoryId : null;
    }

    /** Flip a single transaction between cleared and pending (the status-column toggle). */
    public function toggleCleared(int $id): void
    {
        $t = $this->ownedTransaction($id);
        Gate::authorize('update', $t);

        $t->update(['cleared' => ! $t->cleared]);
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->resetErrorBag();
    }

    public function deleteTransaction(int $id): void
    {
        $t = $this->ownedTransaction($id);
        Gate::authorize('delete', $t);

        $t->delete();

        if ($this->editingId === $id) {
            $this->editingId = null;
        }
    }

    /** Fetch one of the user's cash accounts or 403. */
    private function ownedAccount(int $id): CashAccount
    {
        $account = CashAccount::find($id);
        abort_unless($account && $account->user_id === auth()->id(), 403);

        return $account;
    }

    /** Fetch a transaction that belongs to one of the user's accounts, or 403/404. */
    private function ownedTransaction(?int $id): CashTransaction
    {
        $t = CashTransaction::whereIn('cash_account_id', $this->accountIds())->find($id);
        abort_unless($t, 404);

        return $t;
    }

    public function render()
    {
        return view('livewire.all-transactions');
    }
}

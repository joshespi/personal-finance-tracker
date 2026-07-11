<?php

namespace App\Livewire;

use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Envelope;
use App\Models\IncomeCategory;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class TransactionList extends Component
{
    use WithPagination;

    public CashAccount $account;

    public string $newType = 'deposit';

    public string $newAmount = '';

    public string $newDescription = '';

    public string $newOccurredAt = '';

    public ?int $newEnvelopeId = null;

    public ?int $newIncomeCategoryId = null;

    public bool $newCleared = false;

    public ?int $editingId = null;

    public string $editType = 'deposit';

    public string $editAmount = '';

    public string $editDescription = '';

    public string $editOccurredAt = '';

    public ?int $editEnvelopeId = null;

    public ?int $editIncomeCategoryId = null;

    public bool $editCleared = false;

    public string $filter = '';

    /** Rows per page. */
    public const PER_PAGE = 50;

    public function mount(CashAccount $account): void
    {
        abort_unless($account->user_id === auth()->id(), 403);
        $this->account       = $account;
        $this->newOccurredAt = now()->format('Y-m-d');
    }

    public function getTransactionsProperty()
    {
        return $this->filteredQuery()
            ->with(['envelope:id,name', 'incomeCategory:id,name,color'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);
    }

    /** Account transactions narrowed by the current filter (amount match or description search). */
    private function filteredQuery()
    {
        $query = $this->account->transactions();
        $f     = strtolower(trim($this->filter));

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

    public function updatedFilter(): void
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
        Gate::authorize('update', $this->account);

        $data = $this->validate([
            'newType'             => ['required', 'in:deposit,withdrawal'],
            'newAmount'           => ['required', 'numeric', 'gt:0'],
            'newDescription'      => ['nullable', 'string', 'max:500'],
            'newOccurredAt'       => ['required', 'date'],
            'newEnvelopeId'       => ['nullable', 'integer', Envelope::ownershipRule(auth()->id())],
            'newIncomeCategoryId' => [
                'nullable', 'integer',
                IncomeCategory::ownershipRule(auth()->id()),
            ],
            'newCleared' => ['boolean'],
        ]);

        $this->account->transactions()->create([
            'type'               => $data['newType'],
            'amount'             => $data['newAmount'],
            'description'        => $data['newDescription'] ?: null,
            'occurred_at'        => $data['newOccurredAt'],
            'envelope_id'        => $this->resolveEnvelopeId($data['newEnvelopeId'], $data['newType']),
            'income_category_id' => $this->resolveIncomeCategoryId($data['newIncomeCategoryId'], $data['newType']),
            'cleared'            => $this->newCleared,
        ]);

        $this->reset(['newAmount', 'newDescription', 'newEnvelopeId', 'newIncomeCategoryId', 'newCleared']);
        $this->newOccurredAt = now()->format('Y-m-d');
        $this->resetPage();
    }

    public function startEdit(int $id): void
    {
        $t = $this->account->transactions()->find($id);
        abort_unless($t, 404);

        $this->editingId            = $id;
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
        $t = CashTransaction::find($this->editingId);
        abort_unless($t, 404);
        Gate::authorize('update', $t);

        $data = $this->validate([
            'editType'             => ['required', 'in:deposit,withdrawal'],
            'editAmount'           => ['required', 'numeric', 'gt:0'],
            'editDescription'      => ['nullable', 'string', 'max:500'],
            'editOccurredAt'       => ['required', 'date'],
            'editEnvelopeId'       => ['nullable', 'integer', Envelope::ownershipRule(auth()->id())],
            'editIncomeCategoryId' => [
                'nullable', 'integer',
                IncomeCategory::ownershipRule(auth()->id()),
            ],
            'editCleared' => ['boolean'],
        ]);

        $t->update([
            'type'               => $data['editType'],
            'amount'             => $data['editAmount'],
            'description'        => $data['editDescription'] ?: null,
            'occurred_at'        => $data['editOccurredAt'],
            'envelope_id'        => $this->resolveEnvelopeId($data['editEnvelopeId'], $data['editType']),
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

    /** An envelope only applies to withdrawals; ownership is enforced by validation. */
    private function resolveEnvelopeId(?int $envelopeId, string $type): ?int
    {
        return $type === 'withdrawal' ? $envelopeId : null;
    }

    /** Flip a single transaction between cleared and pending (the status-column toggle). */
    public function toggleCleared(int $id): void
    {
        $t = $this->account->transactions()->find($id);
        abort_unless($t, 404);
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
        $t = CashTransaction::find($id);
        abort_unless($t, 404);
        Gate::authorize('delete', $t);

        $t->delete();

        if ($this->editingId === $id) {
            $this->editingId = null;
        }
    }

    public function render()
    {
        return view('livewire.transaction-list');
    }
}

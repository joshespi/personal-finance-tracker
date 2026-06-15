<?php

namespace App\Livewire;

use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Envelope;
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

    public ?int $editingId = null;
    public string $editType = 'deposit';
    public string $editAmount = '';
    public string $editDescription = '';
    public string $editOccurredAt = '';
    public ?int $editEnvelopeId = null;

    public string $filter = '';

    /** Rows per page. */
    public const PER_PAGE = 50;

    public function mount(CashAccount $account): void
    {
        abort_unless($account->user_id === auth()->id(), 403);
        $this->account = $account;
        $this->newOccurredAt = now()->format('Y-m-d');
    }

    public function getTransactionsProperty()
    {
        return $this->filteredQuery()
            ->with('envelope:id,name')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);
    }

    /** Account transactions narrowed by the current filter (amount match or description search). */
    private function filteredQuery()
    {
        $query = $this->account->transactions();
        $f = strtolower(trim($this->filter));

        if ($f !== '') {
            $asNum = is_numeric($f) ? (float) $f : null;
            if ($asNum !== null && $asNum > 0) {
                $query->whereRaw('ABS(amount - ?) < 0.005', [$asNum]);
            } else {
                $query->whereRaw('LOWER(description) LIKE ?', ['%' . $f . '%']);
            }
        }

        return $query;
    }

    /** Whole-account balance — independent of the filter and pagination. */
    public function getBalanceProperty(): float
    {
        return $this->account->balance();
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

    public function addTransaction(): void
    {
        Gate::authorize('update', $this->account);

        $data = $this->validate([
            'newType'        => ['required', 'in:deposit,withdrawal'],
            'newAmount'      => ['required', 'numeric', 'gt:0'],
            'newDescription' => ['nullable', 'string', 'max:500'],
            'newOccurredAt'  => ['required', 'date'],
            'newEnvelopeId'  => ['nullable', 'integer', 'exists:envelopes,id'],
        ]);

        $envelopeId = null;
        if ($data['newEnvelopeId'] && $data['newType'] === 'withdrawal') {
            $env = Envelope::find($data['newEnvelopeId']);
            abort_unless($env && $env->user_id === auth()->id(), 403);
            $envelopeId = $data['newEnvelopeId'];
        }

        $this->account->transactions()->create([
            'type'        => $data['newType'],
            'amount'      => $data['newAmount'],
            'description' => $data['newDescription'] ?: null,
            'occurred_at' => $data['newOccurredAt'],
            'envelope_id' => $envelopeId,
        ]);

        $this->reset(['newAmount', 'newDescription', 'newEnvelopeId']);
        $this->newOccurredAt = now()->format('Y-m-d');
        $this->resetPage();
    }

    public function startEdit(int $id): void
    {
        $t = $this->account->transactions()->find($id);
        abort_unless($t, 404);

        $this->editingId = $id;
        $this->editType = $t->type;
        $this->editAmount = (string) $t->amount;
        $this->editDescription = $t->description ?? '';
        $this->editOccurredAt = $t->occurred_at->format('Y-m-d');
        $this->editEnvelopeId = $t->envelope_id;

        $this->resetErrorBag();
    }

    public function saveEdit(): void
    {
        $t = CashTransaction::find($this->editingId);
        abort_unless($t, 404);
        Gate::authorize('update', $t);

        $data = $this->validate([
            'editType'        => ['required', 'in:deposit,withdrawal'],
            'editAmount'      => ['required', 'numeric', 'gt:0'],
            'editDescription' => ['nullable', 'string', 'max:500'],
            'editOccurredAt'  => ['required', 'date'],
            'editEnvelopeId'  => ['nullable', 'integer', 'exists:envelopes,id'],
        ]);

        $envelopeId = null;
        if ($data['editEnvelopeId'] && $data['editType'] === 'withdrawal') {
            $env = Envelope::find($data['editEnvelopeId']);
            abort_unless($env && $env->user_id === auth()->id(), 403);
            $envelopeId = $data['editEnvelopeId'];
        }

        $t->update([
            'type'        => $data['editType'],
            'amount'      => $data['editAmount'],
            'description' => $data['editDescription'] ?: null,
            'occurred_at' => $data['editOccurredAt'],
            'envelope_id' => $envelopeId,
        ]);

        $this->editingId = null;
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

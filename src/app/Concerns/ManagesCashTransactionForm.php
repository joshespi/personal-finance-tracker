<?php

namespace App\Concerns;

use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Envelope;
use App\Models\IncomeCategory;
use App\Support\Sql;
use Illuminate\Support\Facades\Gate;

/**
 * Shared add/edit transaction form state, validation, and CRUD for the two cash-ledger
 * Livewire views (single-account TransactionList and all-accounts AllTransactions).
 * The two differ only in *which* account a write targets and how a transaction is
 * looked up under ownership — those are the three hooks below. Everything else (the
 * field set, validation shape, search filter, and envelope/income-category-by-type
 * resolution) is identical between the two.
 */
trait ManagesCashTransactionForm
{
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

    /** Extra validation rules the host needs for its account field (empty if fixed). */
    abstract protected function writeAccountRules(string $prefix): array;

    /** Resolve and authorize the account a new/edited transaction should write to. */
    abstract protected function resolveWriteAccount(array $data, string $prefix): CashAccount;

    /** Fetch a transaction this user is allowed to act on, or abort. */
    abstract protected function ownedTransaction(?int $id): CashTransaction;

    /** Hook for host-specific extra edit-state (AllTransactions also seeds editAccountId). */
    protected function afterStartEdit(CashTransaction $transaction): void
    {
        //
    }

    /**
     * Friendly field names for validation messages. Without these the prefixed property
     * names leak into the UI ("The edit amount field must be greater than 0"), which the
     * inline edit row now surfaces in a full-width error strip under the row.
     */
    protected function validationAttributes(): array
    {
        $labels = [
            'Type'             => 'type',
            'Amount'           => 'amount',
            'Description'      => 'description',
            'OccurredAt'       => 'date',
            'EnvelopeId'       => 'envelope',
            'IncomeCategoryId' => 'category',
            'Cleared'          => 'cleared status',
            'AccountId'        => 'account',
        ];

        return collect($labels)
            ->mapWithKeys(fn (string $label, string $suffix) => ["new{$suffix}" => $label, "edit{$suffix}" => $label])
            ->all();
    }

    /** Validation rules shared by add/edit, keyed with the given field prefix ('new'/'edit'). */
    private function transactionRules(string $prefix): array
    {
        return [
            "{$prefix}Type"             => ['required', 'in:deposit,withdrawal'],
            "{$prefix}Amount"           => ['required', 'numeric', 'gt:0'],
            "{$prefix}Description"      => ['nullable', 'string', 'max:500'],
            "{$prefix}OccurredAt"       => ['required', 'date'],
            "{$prefix}EnvelopeId"       => ['nullable', 'integer', Envelope::ownershipRule(auth()->id())],
            "{$prefix}IncomeCategoryId" => ['nullable', 'integer', IncomeCategory::ownershipRule(auth()->id())],
            "{$prefix}Cleared"          => ['boolean'],
        ];
    }

    /** Apply the amount/description search filter to a transactions query builder. */
    private function applySearchFilter($query)
    {
        $f = strtolower(trim($this->filter));

        if ($f === '') {
            return $query;
        }

        $asNum = is_numeric($f) ? (float) $f : null;
        if ($asNum !== null && $asNum > 0) {
            $query->whereRaw('ABS(amount - ?) < 0.005', [$asNum]);
        } else {
            // Escape LIKE wildcards so a filter like "50%" matches literally, not everything.
            $query->whereRaw('LOWER(description) LIKE ? '.Sql::LIKE_ESCAPE, ['%'.Sql::escapeLike($f).'%']);
        }

        return $query;
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

    public function addTransaction(): void
    {
        $data = $this->validate($this->transactionRules('new') + $this->writeAccountRules('new'));

        $account = $this->resolveWriteAccount($data, 'new');

        $account->transactions()->create([
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
        $t = $this->ownedTransaction($id);

        $this->editingId            = $id;
        $this->editType             = $t->type;
        $this->editAmount           = (string) $t->amount;
        $this->editDescription      = $t->description ?? '';
        $this->editOccurredAt       = $t->occurred_at->format('Y-m-d');
        $this->editEnvelopeId       = $t->envelope_id;
        $this->editIncomeCategoryId = $t->income_category_id;
        $this->editCleared          = (bool) $t->cleared;

        $this->afterStartEdit($t);

        $this->resetErrorBag();
    }

    public function saveEdit(): void
    {
        $t = $this->ownedTransaction($this->editingId);
        Gate::authorize('update', $t);

        $data = $this->validate($this->transactionRules('edit') + $this->writeAccountRules('edit'));

        $account = $this->resolveWriteAccount($data, 'edit');

        // A transfer is one event recorded as two mirrored legs. Its direction and the pair of
        // accounts it spans define it, so neither can be edited from a single side without
        // desyncing the pair (same rule TransactionController applies to portfolio transfers);
        // the shared amount/description/date are editable and pushed to the other leg below.
        if ($t->isTransferLeg()) {
            if ($data['editType'] !== $t->type) {
                $this->addError('editType', "A transfer's direction can't be changed. Delete the transfer and re-enter it.");

                return;
            }
            if ($account->id !== $t->cash_account_id) {
                $this->addError('editAccountId', "A transfer leg can't be moved to a different account. Delete the transfer and re-enter it.");

                return;
            }
        }

        $t->update([
            'cash_account_id'    => $account->id,
            'type'               => $data['editType'],
            'amount'             => $data['editAmount'],
            'description'        => $data['editDescription'] ?: null,
            'occurred_at'        => $data['editOccurredAt'],
            'envelope_id'        => $this->resolveEnvelopeId($data['editEnvelopeId'], $data['editType']),
            'income_category_id' => $this->resolveIncomeCategoryId($data['editIncomeCategoryId'], $data['editType']),
            'cleared'            => $this->editCleared,
        ]);

        $t->syncTransferCounterpart();

        $this->editingId = null;
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

    /** Deletes both legs when the row is part of a transfer — see CashTransaction::deleteWithCounterpart(). */
    public function deleteTransaction(int $id): void
    {
        $t = $this->ownedTransaction($id);
        Gate::authorize('delete', $t);

        $t->deleteWithCounterpart();

        if ($this->editingId === $id) {
            $this->editingId = null;
        }
    }
}

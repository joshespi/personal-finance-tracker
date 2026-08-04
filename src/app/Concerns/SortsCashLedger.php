<?php

namespace App\Concerns;

/**
 * Column sorting for the two cash-ledger Livewire views (single-account
 * TransactionList and all-accounts AllTransactions). Kept separate from
 * ManagesCashTransactionForm — which owns the add/edit form and CRUD — for the
 * same reason FiltersTransactionQuery is separate from its controllers: sorting
 * is a query concern, not a form one. Hosts must also use Livewire's
 * WithPagination, since changing the sort resets to page one.
 */
trait SortsCashLedger
{
    /** Column the ledger is sorted by — a key of self::SORTS. */
    public string $sortField = 'occurred_at';

    /** 'asc' or 'desc'. */
    public string $sortDirection = 'desc';

    /**
     * Sortable columns → the SQL to order by, plus the direction the column reads most
     * naturally in on its first click (dates and amounts largest-first, text A-Z). The
     * key is what the header buttons send and is therefore user input; the expression
     * never is, so nothing user-supplied reaches the raw order-by.
     *
     * Account and category sort by the related row's *name* via a correlated subquery
     * rather than the join used by FiltersTransactionQuery::applyTransactionSort():
     * category has to coalesce across two different tables, and a join would need an
     * explicit re-select to survive the relation queries these ledgers build on.
     * Outflow and inflow zero out the rows of the other type, so the biggest spends
     * (or deposits) rise to the top of their own column.
     */
    private const SORTS = [
        'account' => [
            'sql'     => '(select name from cash_accounts where cash_accounts.id = cash_transactions.cash_account_id)',
            'default' => 'asc',
        ],
        'occurred_at' => ['sql' => 'occurred_at', 'default' => 'desc'],
        'type'        => ['sql' => 'type', 'default' => 'asc'],
        'cleared'     => ['sql' => 'cleared', 'default' => 'desc'],
        'description' => ['sql' => 'description', 'default' => 'asc'],
        'category'    => [
            'sql' => 'coalesce('
                .'(select name from envelopes where envelopes.id = cash_transactions.envelope_id),'
                .'(select name from income_categories where income_categories.id = cash_transactions.income_category_id))',
            'default' => 'asc',
        ],
        'outflow' => ['sql' => "case when type = 'withdrawal' then amount else 0 end", 'default' => 'desc'],
        'inflow'  => ['sql' => "case when type = 'deposit' then amount else 0 end", 'default' => 'desc'],
    ];

    /** Header click: same column flips direction, a new column starts at its natural one. */
    public function sortBy(string $field): void
    {
        if (! array_key_exists($field, self::SORTS)) {
            return;
        }

        $this->sortDirection = $this->sortField === $field
            ? ($this->sortDirection === 'asc' ? 'desc' : 'asc')
            : self::SORTS[$field]['default'];

        $this->sortField = $field;

        $this->resetPage();
    }

    /**
     * Order a transactions query by the current column. Both properties are public and
     * so client-settable — the whitelist lookup and the direction ternary are what make
     * that safe. `id` breaks ties so pagination stays stable across pages.
     */
    private function applySort($query)
    {
        $column    = self::SORTS[$this->sortField] ?? self::SORTS['occurred_at'];
        $direction = $this->sortDirection === 'asc' ? 'asc' : 'desc';

        return $query->orderByRaw("{$column['sql']} {$direction}")->orderBy('id', $direction);
    }
}

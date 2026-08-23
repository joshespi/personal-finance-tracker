{{--
    Transaction list: header/filter row + table (view + inline edit row).
    Params:
      $heading (string), $transactions (paginator), $editingId, $filter, $demo
      $envelopes, $incomeCategories — for the edit row's category selects
      $showAccountColumn (bool) — print an Account column/link and, in the edit row, an
                 account picker (cross-account ledger only)
      $accounts (Collection|null) — required when $showAccountColumn is true, both for the
                 account-filter dropdown and the edit row's account select
      $showAccountFilter (bool) — show the "All accounts" / per-account filter dropdown
      $showStatusFilter (bool), $accountFilter, $statusFilter — status/account filter state
      $emptyFilteredText, $emptyText — empty-state copy when a filter is/isn't active
    Column headers are sortable via the host's sortBy()/$sortField/$sortDirection
    (SortsCashLedger), which both hosts expose as view variables.
    The edit row reads the host's edit* properties (ManagesCashTransactionForm) straight from
    the inherited view scope rather than through the include array, same as $sortField above.
    Shared by transaction-list.blade.php (single account) and all-transactions.blade.php (aggregate).
--}}
<div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg mt-8">
    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex flex-wrap items-center gap-3 justify-between">
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $heading }}</h3>

        @if ($showAccountFilter || $showStatusFilter)
            <div class="flex items-center gap-2 flex-wrap ml-auto">
                @if ($showAccountFilter)
                    <select wire:model.live="accountFilter"
                            class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                        <option value="">All accounts</option>
                        @foreach ($accounts as $acct)
                            <option value="{{ $acct->id }}">{{ $demo->n($acct->name) }}</option>
                        @endforeach
                    </select>
                @endif

                @if ($showStatusFilter)
                    <select wire:model.live="statusFilter"
                            class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                        <option value="">All statuses</option>
                        <option value="pending">Pending only</option>
                        <option value="cleared">Cleared only</option>
                    </select>
                @endif

                <div class="flex items-center gap-2 sm:min-w-[18rem]">
                    <input type="search" wire:model.live.debounce.300ms="filter"
                           placeholder="Filter: 45.32 or whole foods"
                           class="flex-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" />
                    <div wire:loading wire:target="filter" class="text-xs text-gray-400 dark:text-gray-500">…</div>
                    @if ($filter)
                        <button type="button" wire:click="$set('filter', '')"
                                class="text-xs text-gray-500 dark:text-gray-400 hover:underline">Clear</button>
                    @endif
                </div>
            </div>
        @elseif ($transactions->isNotEmpty() || $filter)
            <div class="flex items-center gap-2 flex-1 sm:flex-none sm:min-w-[18rem] max-w-md ml-auto">
                <input type="search" wire:model.live.debounce.300ms="filter"
                       placeholder="Filter: 45.32 or whole foods"
                       class="flex-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" />
                <div wire:loading wire:target="filter" class="text-xs text-gray-400 dark:text-gray-500">…</div>
                @if ($filter)
                    <button type="button" wire:click="$set('filter', '')"
                            class="text-xs text-gray-500 dark:text-gray-400 hover:underline">Clear</button>
                @endif
            </div>
        @endif
    </div>

    @if ($transactions->isEmpty())
        <div class="p-6 text-sm text-gray-500 dark:text-gray-400">
            {{ $filter || $accountFilter || $statusFilter ? $emptyFilteredText : $emptyText }}
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        @if ($showAccountColumn)
                            <x-sort-th field="account" label="Account" :current="$sortField" :direction="$sortDirection" />
                        @endif
                        <x-sort-th field="occurred_at" label="Date" :current="$sortField" :direction="$sortDirection" />
                        <x-sort-th field="type" label="Type" :current="$sortField" :direction="$sortDirection" />
                        <x-sort-th field="cleared" label="Status" :current="$sortField" :direction="$sortDirection" />
                        <x-sort-th field="description" label="Description" :current="$sortField" :direction="$sortDirection" />
                        <x-sort-th field="category" label="Envelope / Category" :current="$sortField" :direction="$sortDirection" />
                        <x-sort-th field="outflow" label="Outflow" align="right" :current="$sortField" :direction="$sortDirection" />
                        <x-sort-th field="inflow" label="Inflow" align="right" :current="$sortField" :direction="$sortDirection" />
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($transactions as $t)
                        @if ($editingId === $t->id)
                            @php
                                // Inline controls stay fluid with a modest min-width: fixed widths made the
                                // edit row wider than the table, pushing Save/Cancel off the right edge.
                                $ctl        = 'block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm text-sm py-1.5 focus:border-indigo-500 focus:ring-indigo-500';
                                $editCtl    = $ctl.' text-gray-900 dark:text-gray-200';
                                $editErrors = collect($errors->get('edit*'))->flatten()->all();
                            @endphp
                            <tr wire:key="txn-{{ $t->id }}" class="bg-indigo-50 dark:bg-indigo-900/20"
                                x-data="{ etype: @entangle('editType') }"
                                x-on:keydown.enter.prevent="$wire.saveEdit()"
                                x-on:keydown.escape="$wire.cancelEdit()">
                                @if ($showAccountColumn)
                                    <td class="px-4 py-2">
                                        <select wire:model="editAccountId" aria-label="Account"
                                                class="{{ $editCtl }} min-w-[7rem]">
                                            @foreach ($accounts as $acct)
                                                <option value="{{ $acct->id }}">{{ $demo->n($acct->name) }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                @endif
                                <td class="px-4 py-2">
                                    <input type="date" wire:model="editOccurredAt" aria-label="Date"
                                           class="{{ $editCtl }} min-w-[8rem]" />
                                </td>
                                <td class="px-4 py-2">
                                    <select wire:model="editType" x-model="etype" aria-label="Type"
                                            class="{{ $editCtl }} min-w-[6.5rem]">
                                        <option value="deposit">Deposit</option>
                                        <option value="withdrawal">Withdrawal</option>
                                    </select>
                                </td>
                                <td class="px-4 py-2">
                                    <x-cleared-pill :cleared="$editCleared" wire:click="$toggle('editCleared')" />
                                </td>
                                <td class="px-4 py-2">
                                    {{-- size="1" drops the input's intrinsic 20-character width so the column
                                         can shrink to its min-width instead of over-claiming table space. --}}
                                    <input type="text" wire:model="editDescription" maxlength="500" size="1"
                                           aria-label="Description" placeholder="Description"
                                           class="{{ $editCtl }} min-w-[6rem]" />
                                </td>
                                <td class="px-4 py-2">
                                    <select wire:model="editEnvelopeId" x-show="etype === 'withdrawal'" aria-label="Envelope"
                                            class="{{ $editCtl }} min-w-[7.5rem]">
                                        <option value="">— None —</option>
                                        @foreach ($envelopes as $env)
                                            <option value="{{ $env->id }}">{{ $env->name }}</option>
                                        @endforeach
                                    </select>
                                    <select wire:model="editIncomeCategoryId" x-show="etype === 'deposit'" x-cloak aria-label="Income category"
                                            class="{{ $editCtl }} min-w-[7.5rem]">
                                        <option value="">— Uncategorized —</option>
                                        @foreach ($incomeCategories as $cat)
                                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                {{-- One amount field spanning Outflow/Inflow; its colour tracks the type the
                                     way the two view columns do. --}}
                                <td class="px-4 py-2" colspan="2">
                                    <div class="relative ml-auto w-full max-w-[8rem] min-w-[5.5rem]">
                                        <span class="pointer-events-none absolute inset-y-0 left-2 flex items-center text-xs text-gray-400 dark:text-gray-500">$</span>
                                        <input type="number" wire:model="editAmount" min="0" step="any" size="1"
                                               inputmode="decimal" aria-label="Amount" placeholder="0.00"
                                               class="{{ $ctl }} no-spinner pl-5 text-right font-mono font-semibold"
                                               x-bind:class="etype === 'deposit' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'" />
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1">
                                        <button type="button" wire:click="saveEdit"
                                                class="inline-flex items-center px-2.5 py-1 bg-indigo-600 text-white text-xs font-semibold rounded hover:bg-indigo-500 transition">
                                            Save
                                        </button>
                                        <button type="button" wire:click="cancelEdit" title="Cancel (Esc)"
                                                class="inline-flex items-center px-2.5 py-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                                            Cancel
                                        </button>
                                    </span>
                                </td>
                            </tr>
                            @if ($editErrors)
                                {{-- Errors get their own full-width row: inside a narrow cell they wrap and
                                     stretch the column, which is what broke the layout before. --}}
                                <tr wire:key="txn-errors-{{ $t->id }}" class="bg-indigo-50 dark:bg-indigo-900/20 !border-t-0">
                                    {{-- colspan 99: browsers clamp it to the real column count, so the
                                         header's column list stays the only place columns are counted. --}}
                                    <td colspan="99" class="px-4 pb-2">
                                        <x-input-error :messages="$editErrors" />
                                    </td>
                                </tr>
                            @endif
                        @else
                            <tr wire:key="txn-{{ $t->id }}"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer group"
                                wire:click="startEdit({{ $t->id }})">
                                @if ($showAccountColumn)
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                        @if ($t->cashAccount)
                                            <a href="{{ route('cash-accounts.show', $t->cashAccount) }}"
                                               wire:click.stop
                                               class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $demo->n($t->cashAccount->name) }}</a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                @endif
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $t->occurred_at->format('M j, Y') }}</td>
                                <x-transaction-type-cell :transaction="$t" />
                                <td class="px-4 py-3">
                                    <x-cleared-pill :cleared="$t->cleared" wire:click.stop="toggleCleared({{ $t->id }})" />
                                </td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $t->description ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">
                                    @if ($t->envelope)
                                        <a href="{{ route('envelopes.show', $t->envelope) }}"
                                           wire:click.stop
                                           class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">{{ $demo->n($t->envelope->name) }}</a>
                                    @elseif ($t->incomeCategory)
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                            <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $t->incomeCategory->color }}"></span>
                                            {{ $t->incomeCategory->name }}
                                        </span>
                                    @else
                                        <span class="text-gray-300 dark:text-gray-600">—</span>
                                    @endif
                                </td>
                                <x-flow-cells :inflow="$t->type === 'deposit'" :amount="$demo->amt((float) $t->amount)" cell-class="px-4 py-3" />
                                <td class="px-4 py-3 text-right">
                                    <button wire:click.stop="deleteTransaction({{ $t->id }})"
                                            wire:confirm="Delete this transaction?"
                                            class="text-gray-400 dark:text-gray-500 hover:text-red-600 dark:hover:text-red-400 hover:underline text-xs transition-colors">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($transactions->hasPages())
            <div class="px-6 py-3 border-t border-gray-100 dark:border-gray-700">
                {{ $transactions->links() }}
            </div>
        @endif
    @endif
</div>

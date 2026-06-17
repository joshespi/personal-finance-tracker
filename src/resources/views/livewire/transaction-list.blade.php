<div>
    {{-- Balance card: Cleared + Pending = Working --}}
    @php $bal = $this->balances; @endphp
    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
        <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Cleared Balance</p>
                <p class="mt-1 text-xl font-semibold font-mono {{ $bal['cleared'] >= 0 ? 'text-gray-900 dark:text-gray-100' : 'text-red-600' }}">
                    {{ $bal['cleared'] < 0 ? '−' : '' }}${{ $demo->amt(abs($bal['cleared'])) }}
                </p>
            </div>
            <span class="text-2xl text-gray-300 dark:text-gray-600 font-light">+</span>
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Pending Balance</p>
                <p class="mt-1 text-xl font-semibold font-mono {{ $bal['uncleared'] == 0 ? 'text-gray-400 dark:text-gray-500' : ($bal['uncleared'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600') }}">
                    {{ $bal['uncleared'] < 0 ? '−' : '' }}${{ $demo->amt(abs($bal['uncleared'])) }}
                </p>
            </div>
            <span class="text-2xl text-gray-300 dark:text-gray-600 font-light">=</span>
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Working Balance</p>
                <p class="mt-1 text-3xl font-semibold font-mono {{ $bal['working'] >= 0 ? 'text-gray-900 dark:text-gray-100' : 'text-red-600' }}">
                    {{ $bal['working'] < 0 ? '−' : '' }}${{ $demo->amt(abs($bal['working'])) }}
                </p>
            </div>
        </div>
    </div>

    {{-- Scheduled --}}
    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg mt-8">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Scheduled</h3>
            <a href="{{ route('scheduled-transactions.create') }}"
               class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">+ New</a>
        </div>

        @if ($this->scheduled->isEmpty())
            <div class="p-6 text-sm text-gray-500 dark:text-gray-400">
                No scheduled transactions linked to this account.
                <a href="{{ route('scheduled-transactions.create') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Create one</a>
                to auto-record recurring deposits or expenses.
            </div>
        @else
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach ($this->scheduled as $s)
                    @php $isDue = $s->next_due_at->isToday(); $isOverdue = $s->next_due_at->isPast() && !$s->next_due_at->isToday(); @endphp
                    <div class="px-6 py-3 flex items-center gap-4">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $s->description }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                {{ $s->typeLabel() }} · {{ $s->recurrenceLabel() }}
                                @if ($s->envelope)
                                    · <span class="inline-block w-2 h-2 rounded-full align-middle" style="background-color: {{ $s->envelope->color }}"></span>
                                    {{ $demo->n($s->envelope->name) }}
                                @endif
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-sm font-mono font-semibold text-gray-900 dark:text-gray-100">${{ $demo->amt((float)$s->amount) }}</p>
                            <p class="text-xs mt-0.5 {{ ($isDue || $isOverdue) ? 'text-amber-600 dark:text-amber-400 font-medium' : 'text-gray-500 dark:text-gray-400' }}">
                                @if ($isOverdue) Overdue · @elseif ($isDue) Due · @endif
                                {{ $s->next_due_at->format('M j, Y') }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <form method="POST" action="{{ route('scheduled-transactions.enter-now', $s) }}"
                                  onsubmit="return confirm('Record &quot;{{ $s->description }}&quot; (${{ $demo->amt((float)$s->amount) }}) now and advance to the next cycle?')">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center px-2.5 py-1 bg-indigo-600 border border-transparent rounded-md text-xs font-semibold text-white hover:bg-indigo-500 transition">
                                    Enter now
                                </button>
                            </form>
                            <form method="POST" action="{{ route('scheduled-transactions.skip', $s) }}"
                                  onsubmit="return confirm('Skip this occurrence without recording it?')">
                                @csrf
                                <button type="submit"
                                        class="text-xs text-gray-400 dark:text-gray-500 hover:underline">Skip</button>
                            </form>
                            <a href="{{ route('scheduled-transactions.edit', $s) }}"
                               class="text-xs text-gray-400 dark:text-gray-500 hover:underline">Edit</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Add Transaction --}}
    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg mt-8">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Record Transaction</h3>
        </div>
        <div class="p-6 space-y-3">
            <form wire:submit="addTransaction"
                  x-data="{ type: @entangle('newType') }"
                  class="flex flex-wrap items-end gap-4">
                @csrf

                <div>
                    <x-input-label for="newType" value="Type" />
                    <select id="newType" wire:model="newType" x-model="type"
                            class="mt-1 block w-36 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="deposit">Deposit</option>
                        <option value="withdrawal">Withdrawal</option>
                    </select>
                    <x-input-error :messages="$errors->get('newType')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="newAmount" value="Amount ({{ $account->currency }})" />
                    <x-text-input id="newAmount" wire:model="newAmount" type="number" class="mt-1 block w-40"
                                  required min="0" step="any" placeholder="0.00" />
                    <x-input-error :messages="$errors->get('newAmount')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="newOccurredAt" value="Date" />
                    <x-text-input id="newOccurredAt" wire:model="newOccurredAt" type="date" class="mt-1 block w-40" required />
                    <x-input-error :messages="$errors->get('newOccurredAt')" class="mt-2" />
                </div>

                <div class="flex-1 min-w-48">
                    <x-input-label for="newDescription" value="Description (optional)" />
                    <x-text-input id="newDescription" wire:model="newDescription" type="text" class="mt-1 block w-full"
                                  maxlength="500" placeholder="Paycheck, rent, groceries…" />
                    <x-input-error :messages="$errors->get('newDescription')" class="mt-2" />
                </div>

                @if ($this->envelopes->isNotEmpty())
                    <div x-show="type === 'withdrawal'" x-cloak>
                        <x-input-label for="newEnvelopeId" value="Charge to envelope (optional)" />
                        <select id="newEnvelopeId" wire:model="newEnvelopeId"
                                class="mt-1 block w-52 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">— None —</option>
                            @foreach ($this->envelopes as $env)
                                <option value="{{ $env->id }}">{{ $env->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('newEnvelopeId')" class="mt-2" />
                    </div>
                @endif

                <div x-show="type === 'deposit'" x-cloak>
                    <x-input-label for="newIncomeCategoryId" value="Income category (optional)" />
                    @if ($this->incomeCategories->isNotEmpty())
                        <select id="newIncomeCategoryId" wire:model="newIncomeCategoryId"
                                class="mt-1 block w-52 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">— Uncategorized —</option>
                            @foreach ($this->incomeCategories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    @else
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 w-52">
                            <a href="{{ route('income-categories.create') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Add categories</a>
                            to label your income.
                        </p>
                    @endif
                    <x-input-error :messages="$errors->get('newIncomeCategoryId')" class="mt-2" />
                </div>

                <div class="pb-2">
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none" title="Leave unchecked for a pending transaction that hasn't cleared the bank yet">
                        <input type="checkbox" wire:model="newCleared"
                               class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-indigo-600 focus:ring-indigo-500" />
                        <span class="text-sm text-gray-700 dark:text-gray-300">Cleared</span>
                    </label>
                </div>

                <div class="pb-0.5">
                    <x-primary-button>Record</x-primary-button>
                </div>
            </form>
        </div>
    </div>

    {{-- Transaction list --}}
    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg mt-8">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex flex-wrap items-center gap-3 justify-between">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Transactions</h3>

            @if ($this->transactions->isNotEmpty() || $filter)
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

        @if ($this->transactions->isEmpty())
            <div class="p-6 text-sm text-gray-500 dark:text-gray-400">
                {{ $filter ? 'No transactions match the current filter.' : 'No transactions yet.' }}
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Envelope / Category</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Amount</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($this->transactions as $t)
                            @if ($editingId === $t->id)
                                <tr class="bg-indigo-50 dark:bg-indigo-900/20" x-data="{ etype: @entangle('editType') }">
                                    <td class="px-4 py-2">
                                        <input type="date" wire:model="editOccurredAt"
                                               class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        <x-input-error :messages="$errors->get('editOccurredAt')" class="mt-1" />
                                    </td>
                                    <td class="px-4 py-2">
                                        <select wire:model="editType" x-model="etype"
                                                class="block w-32 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="deposit">Deposit</option>
                                            <option value="withdrawal">Withdrawal</option>
                                        </select>
                                        <x-input-error :messages="$errors->get('editType')" class="mt-1" />
                                    </td>
                                    <td class="px-4 py-2">
                                        <label class="inline-flex items-center gap-1.5 cursor-pointer select-none">
                                            <input type="checkbox" wire:model="editCleared"
                                                   class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-indigo-600 focus:ring-indigo-500" />
                                            <span class="text-xs text-gray-600 dark:text-gray-400">Cleared</span>
                                        </label>
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="text" wire:model="editDescription" maxlength="500"
                                               placeholder="Description"
                                               class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        <x-input-error :messages="$errors->get('editDescription')" class="mt-1" />
                                    </td>
                                    <td class="px-4 py-2">
                                        <select wire:model="editEnvelopeId" x-show="etype === 'withdrawal'"
                                                class="block w-44 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="">— None —</option>
                                            @foreach ($this->envelopes as $env)
                                                <option value="{{ $env->id }}">{{ $env->name }}</option>
                                            @endforeach
                                        </select>
                                        <select wire:model="editIncomeCategoryId" x-show="etype === 'deposit'" x-cloak
                                                class="block w-44 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="">— Uncategorized —</option>
                                            @foreach ($this->incomeCategories as $cat)
                                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                            @endforeach
                                        </select>
                                        <x-input-error :messages="$errors->get('editEnvelopeId')" class="mt-1" />
                                        <x-input-error :messages="$errors->get('editIncomeCategoryId')" class="mt-1" />
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <input type="number" wire:model="editAmount" min="0" step="any"
                                               placeholder="0.00"
                                               class="block w-28 ml-auto border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm text-sm text-right font-mono focus:border-indigo-500 focus:ring-indigo-500" />
                                        <x-input-error :messages="$errors->get('editAmount')" class="mt-1" />
                                    </td>
                                    <td class="px-4 py-2 text-right whitespace-nowrap">
                                        <button wire:click="saveEdit"
                                                class="inline-flex items-center px-2.5 py-1 bg-indigo-600 text-white text-xs font-semibold rounded hover:bg-indigo-500 transition">
                                            Save
                                        </button>
                                        <button wire:click="cancelEdit" type="button"
                                                class="ml-1 inline-flex items-center px-2.5 py-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                                            Cancel
                                        </button>
                                    </td>
                                </tr>
                            @else
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer group"
                                    wire:click="startEdit({{ $t->id }})">
                                    <td class="px-6 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $t->occurred_at->format('M j, Y') }}</td>
                                    <td class="px-6 py-3">
                                        @if ($t->type === 'deposit')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300">Deposit</span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300">Withdrawal</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3">
                                        @php
                                            [$dot, $badge, $label, $title] = $t->cleared
                                                ? ['bg-green-500', 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300 hover:bg-green-200 dark:hover:bg-green-900/70', 'Cleared', 'Cleared — click to mark pending']
                                                : ['bg-amber-500', 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300 hover:bg-amber-200 dark:hover:bg-amber-900/70', 'Pending', 'Pending — click to mark cleared'];
                                        @endphp
                                        <button type="button" wire:click.stop="toggleCleared({{ $t->id }})" title="{{ $title }}"
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium transition {{ $badge }}">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $dot }}"></span> {{ $label }}
                                        </button>
                                    </td>
                                    <td class="px-6 py-3 text-gray-500 dark:text-gray-400">{{ $t->description ?? '—' }}</td>
                                    <td class="px-6 py-3 text-gray-500 dark:text-gray-400">
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
                                    <td class="px-6 py-3 text-right font-mono font-semibold {{ $t->type === 'deposit' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ $t->type === 'deposit' ? '+' : '−' }}{{ $demo->amt((float) $t->amount) }}
                                    </td>
                                    <td class="px-6 py-3 text-right">
                                        <button wire:click.stop="deleteTransaction({{ $t->id }})"
                                                wire:confirm="Delete this transaction?"
                                                class="text-red-600 dark:text-red-400 hover:underline text-xs opacity-0 group-hover:opacity-100 transition-opacity">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($this->transactions->hasPages())
                <div class="px-6 py-3 border-t border-gray-100 dark:border-gray-700">
                    {{ $this->transactions->links() }}
                </div>
            @endif
        @endif
    </div>
</div>

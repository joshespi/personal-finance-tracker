<div>
    {{-- Balance card --}}
    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Current Balance</p>
        <p class="mt-1 text-3xl font-semibold font-mono {{ $this->balance >= 0 ? 'text-gray-900 dark:text-gray-100' : 'text-red-600' }}">
            {{ $this->balance < 0 ? '−' : '' }}${{ $demo->amt(abs($this->balance)) }}
        </p>
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

            @if ($this->transactions->isNotEmpty())
                <div class="flex items-center gap-2 flex-1 sm:flex-none sm:min-w-[18rem] max-w-md ml-auto">
                    <input type="search" wire:model.live.debounce.150ms="filter"
                           placeholder="Filter: 45.32 or whole foods"
                           class="flex-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" />
                    @if ($filter)
                        <button type="button" wire:click="$set('filter', '')"
                                class="text-xs text-gray-500 dark:text-gray-400 hover:underline">Clear</button>
                    @endif
                </div>
            @endif
        </div>

        @php
            $filtered = $this->transactions->filter(function ($t) {
                if (! $this->filter) return true;
                $f = strtolower(trim($this->filter));
                $asNum = is_numeric($f) ? (float) $f : null;
                if ($asNum !== null && $asNum > 0) {
                    return abs((float) $t->amount - $asNum) < 0.005;
                }
                return str_contains(strtolower($t->description ?? ''), $f);
            });
        @endphp

        @if ($this->transactions->isEmpty())
            <div class="p-6 text-sm text-gray-500 dark:text-gray-400">No transactions yet.</div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Envelope</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Amount</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($filtered as $t)
                            @if ($editingId === $t->id)
                                <tr class="bg-indigo-50 dark:bg-indigo-900/20">
                                    <td class="px-4 py-2">
                                        <input type="date" wire:model="editOccurredAt"
                                               class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        <x-input-error :messages="$errors->get('editOccurredAt')" class="mt-1" />
                                    </td>
                                    <td class="px-4 py-2">
                                        <select wire:model="editType"
                                                class="block w-32 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="deposit">Deposit</option>
                                            <option value="withdrawal">Withdrawal</option>
                                        </select>
                                        <x-input-error :messages="$errors->get('editType')" class="mt-1" />
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="text" wire:model="editDescription" maxlength="500"
                                               placeholder="Description"
                                               class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        <x-input-error :messages="$errors->get('editDescription')" class="mt-1" />
                                    </td>
                                    <td class="px-4 py-2">
                                        <select wire:model="editEnvelopeId"
                                                class="block w-44 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="">— None —</option>
                                            @foreach ($this->envelopes as $env)
                                                <option value="{{ $env->id }}">{{ $env->name }}</option>
                                            @endforeach
                                        </select>
                                        <x-input-error :messages="$errors->get('editEnvelopeId')" class="mt-1" />
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
                                    <td class="px-6 py-3 text-gray-500 dark:text-gray-400">{{ $t->description ?? '—' }}</td>
                                    <td class="px-6 py-3 text-gray-500 dark:text-gray-400">
                                        @if ($t->envelope)
                                            <a href="{{ route('envelopes.show', $t->envelope) }}"
                                               wire:click.stop
                                               class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">{{ $demo->n($t->envelope->name) }}</a>
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
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 text-center">
                                    No transactions match the current filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

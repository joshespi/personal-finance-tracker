<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    <a href="{{ route('cash-accounts.index') }}" class="hover:underline">Cash Accounts</a>
                </p>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ $account->name }}</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ $accountTypes[$account->account_type] ?? $account->account_type }} &bull; {{ $account->currency }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('cash-accounts.edit', $account) }}"
                   class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                    Edit
                </a>
                <form method="POST" action="{{ route('cash-accounts.destroy', $account) }}"
                      onsubmit="return confirm('Delete this account and all its transactions?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="inline-flex items-center px-3 py-1.5 bg-red-600 border border-transparent rounded-md text-xs font-semibold text-white hover:bg-red-500 transition">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-12" x-data="cashFilter({ windowDays: 30 })">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @if (session('success'))
                <div class="bg-green-100 dark:bg-green-900/40 border border-green-300 dark:border-green-700 text-green-800 dark:text-green-300 rounded-md px-4 py-3 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Current Balance</p>
                <p class="mt-1 text-3xl font-semibold font-mono {{ $account->current_balance >= 0 ? 'text-gray-900 dark:text-gray-100' : 'text-red-600' }}">
                    {{ $account->current_balance < 0 ? '−' : '' }}${{ number_format(abs($account->current_balance), 2) }}
                </p>
            </div>

            @if ($account->notes)
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $account->notes }}</p>
                </div>
            @endif

            {{-- Add Transaction --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Record Transaction</h3>
                </div>
                <div class="p-6 space-y-3">
                    <form method="POST" action="{{ route('cash-accounts.transactions.store', $account) }}"
                          x-data="{ type: '{{ old('type', 'deposit') }}' }"
                          class="flex flex-wrap items-end gap-4">
                        @csrf

                        <div>
                            <x-input-label for="type" value="Type" />
                            <select id="type" name="type" x-model="type"
                                    class="mt-1 block w-36 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="deposit">Deposit</option>
                                <option value="withdrawal">Withdrawal</option>
                            </select>
                            <x-input-error :messages="$errors->get('type')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="amount" value="Amount ({{ $account->currency }})" />
                            <x-text-input id="amount" name="amount" type="number" class="mt-1 block w-40"
                                          :value="old('amount')" required min="0" step="any" placeholder="0.00"
                                          x-model="entryAmount" />
                            <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="occurred_at" value="Date" />
                            <x-text-input id="occurred_at" name="occurred_at" type="date" class="mt-1 block w-40"
                                          :value="old('occurred_at', now()->format('Y-m-d'))" required />
                            <x-input-error :messages="$errors->get('occurred_at')" class="mt-2" />
                        </div>

                        <div class="flex-1 min-w-48">
                            <x-input-label for="description" value="Description (optional)" />
                            <x-text-input id="description" name="description" type="text" class="mt-1 block w-full"
                                          :value="old('description')" maxlength="500" placeholder="Paycheck, rent, groceries…" />
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>

                        @if ($envelopes->isNotEmpty())
                            <div x-show="type === 'withdrawal'" x-cloak>
                                <x-input-label for="envelope_id" value="Charge to envelope (optional)" />
                                <select id="envelope_id" name="envelope_id"
                                        class="mt-1 block w-52 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">— None —</option>
                                    @foreach ($envelopes as $env)
                                        <option value="{{ $env->id }}" @selected((string)old('envelope_id') === (string)$env->id)>{{ $env->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('envelope_id')" class="mt-2" />
                            </div>
                        @endif

                        <div class="pb-0.5">
                            <x-primary-button>Record</x-primary-button>
                        </div>
                    </form>

                    {{-- Duplicate hint: shown when entry amount matches existing transaction(s) within ±30 days --}}
                    <div x-cloak x-show="dupCount > 0"
                         class="flex items-center justify-between gap-3 rounded-md border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/30 px-3 py-2 text-sm text-amber-800 dark:text-amber-200">
                        <span>
                            ⚠
                            <span x-text="dupCount"></span>
                            existing transaction<span x-show="dupCount > 1">s</span>
                            match this amount within 30 days.
                        </span>
                        <button type="button" @click="showDupsInList()"
                                class="text-xs font-semibold underline hover:no-underline">
                            Show in list
                        </button>
                    </div>
                </div>
            </div>

            {{-- Transaction History --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex flex-wrap items-center gap-3 justify-between">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Transactions</h3>

                    @if ($account->transactions->isNotEmpty())
                        <div class="flex items-center gap-2 flex-1 sm:flex-none sm:min-w-[18rem] max-w-md ml-auto">
                            <input id="tx-filter" type="search" x-model.debounce.150ms="filter"
                                   placeholder="Filter: 45.32 or whole foods"
                                   class="flex-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" />
                            <button type="button" x-show="filter" @click="filter = ''"
                                    class="text-xs text-gray-500 dark:text-gray-400 hover:underline">Clear</button>
                            <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                <span x-text="visibleCount"></span> / {{ $account->transactions->count() }}
                            </span>
                        </div>
                    @endif
                </div>

                @if ($account->transactions->isEmpty())
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
                                @foreach ($account->transactions as $t)
                                    <tr data-amount="{{ (float) $t->amount }}"
                                        data-desc="{{ strtolower($t->description ?? '') }}"
                                        data-date="{{ $t->occurred_at->toDateString() }}"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
                                        x-show="rowVisible($el)"
                                        :class="rowIsDup($el) ? 'bg-amber-50 dark:bg-amber-900/20' : ''">
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
                                                   class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">{{ $t->envelope->name }}</a>
                                            @else
                                                <span class="text-gray-300 dark:text-gray-600">—</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-3 text-right font-mono font-semibold {{ $t->type === 'deposit' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                            {{ $t->type === 'deposit' ? '+' : '−' }}{{ number_format((float)$t->amount, 2) }}
                                        </td>
                                        <td class="px-6 py-3 text-right">
                                            <form method="POST" action="{{ route('cash-accounts.transactions.destroy', $t) }}" class="inline"
                                                  onsubmit="return confirm('Delete this transaction?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 dark:text-red-400 hover:underline text-xs">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                                <tr x-cloak x-show="visibleCount === 0">
                                    <td colspan="5" class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 text-center">
                                        No transactions match the current filter.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('cashFilter', (opts = {}) => ({
            windowDays:   opts.windowDays ?? 30,
            entryAmount:  '',
            filter:       '',
            visibleCount: 0,
            dupCount:     0,

            init() {
                this.$nextTick(() => this.recompute());
                this.$watch('filter',      () => this.recompute());
                this.$watch('entryAmount', () => this.recompute());
            },

            parseAmount(s) {
                if (s === null || s === undefined) return NaN;
                const n = parseFloat(String(s).replace(/[$,\s]/g, ''));
                return isFinite(n) ? n : NaN;
            },

            rowVisible(el) {
                const f = (this.filter ?? '').trim().toLowerCase();
                if (!f) return true;
                const asNum = this.parseAmount(f);
                const amt   = parseFloat(el.dataset.amount);
                if (!isNaN(asNum) && asNum > 0) {
                    return Math.abs(amt - asNum) < 0.005;
                }
                return (el.dataset.desc || '').includes(f);
            },

            rowIsDup(el) {
                const a = this.parseAmount(this.entryAmount);
                if (!a || a <= 0) return false;
                const amt = parseFloat(el.dataset.amount);
                if (Math.abs(amt - a) > 0.005) return false;
                const t  = Date.parse(el.dataset.date);
                if (isNaN(t)) return false;
                const days = Math.abs((Date.now() - t) / 86_400_000);
                return days <= this.windowDays;
            },

            recompute() {
                const rows = this.$root.querySelectorAll('tbody tr[data-amount]');
                let v = 0, d = 0;
                rows.forEach(r => {
                    if (this.rowVisible(r)) v++;
                    if (this.rowIsDup(r))   d++;
                });
                this.visibleCount = v;
                this.dupCount     = d;
            },

            showDupsInList() {
                this.filter = String(this.entryAmount).replace(/[^\d.]/g, '');
                const el = document.getElementById('tx-filter');
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    el.focus();
                }
            },
        }));
    });
    </script>
</x-app-layout>

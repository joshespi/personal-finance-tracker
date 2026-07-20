<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between" x-data>
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    <a href="{{ route('cash-accounts.index') }}" class="hover:underline">Spending Accounts</a>
                </p>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ $demo->n($account->name) }}</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ $accountTypes[$account->account_type] ?? $account->account_type }} &bull; {{ $account->currency }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('cash-transfers.create', ['to_account_id' => $account->id]) }}"
                   class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                    Transfer
                </a>
                <button type="button" @click="$store.reconcile.open = true"
                        class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                    Reconcile
                </button>
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

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @if (session('success'))
                <div class="bg-green-100 dark:bg-green-900/40 border border-green-300 dark:border-green-700 text-green-800 dark:text-green-300 rounded-md px-4 py-3 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if ($account->account_type === 'credit_card' && ($account->interest_rate || $account->billing_day))
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4 flex flex-wrap gap-6 text-sm">
                    @if ($account->interest_rate)
                        @php $monthlyInterest = round(abs($account->current_balance) * ((float)$account->interest_rate / 100) / 12, 2); @endphp
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">APR</p>
                            <p class="mt-0.5 font-mono font-semibold text-gray-900 dark:text-gray-100">{{ $account->interest_rate }}%</p>
                        </div>
                        @if ($account->current_balance < 0)
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Est. monthly interest</p>
                                <p class="mt-0.5 font-mono font-semibold text-red-600 dark:text-red-400">${{ $demo->amt($monthlyInterest) }}</p>
                            </div>
                        @endif
                    @endif
                    @if ($account->billing_day)
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Statement closes</p>
                            <p class="mt-0.5 font-semibold text-gray-900 dark:text-gray-100">{{ $account->billing_day }}{{ match(true) { $account->billing_day === 1 => 'st', $account->billing_day === 2 => 'nd', $account->billing_day === 3 => 'rd', default => 'th' } }} of month</p>
                        </div>
                    @endif
                </div>
            @endif

            @if ($account->notes)
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $account->notes }}</p>
                </div>
            @endif

            @if (! is_null($savingsEnvelopes))
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Savings Reconciliation</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">What this account should hold vs. what it actually holds, based on the envelopes linked to it.</p>
                    </div>

                    @if ($savingsEnvelopes->isEmpty())
                        <div class="p-6 text-sm text-gray-500 dark:text-gray-400">
                            No envelopes are linked to this account yet. Set an envelope's "Lives in account" field to track an emergency fund or savings goal here.
                        </div>
                    @else
                        <div class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($savingsEnvelopes as $e)
                                <div class="px-6 py-3 flex items-center justify-between text-sm">
                                    <a href="{{ route('envelopes.show', $e) }}" class="flex items-center gap-2 hover:underline">
                                        <span class="inline-block w-2.5 h-2.5 rounded-full" style="background-color: {{ $e->color }}"></span>
                                        <span class="text-gray-700 dark:text-gray-300">{{ $e->name }}</span>
                                    </a>
                                    <span class="font-mono text-gray-900 dark:text-gray-100">${{ $demo->amt($e->current_balance) }}</span>
                                </div>
                            @endforeach
                        </div>

                        <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-700 grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Should hold</p>
                                <p class="mt-0.5 font-mono font-semibold text-gray-900 dark:text-gray-100">${{ $demo->amt($expectedTotal) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Actually holds</p>
                                <p class="mt-0.5 font-mono font-semibold text-gray-900 dark:text-gray-100">${{ $demo->amt($account->current_balance) }}</p>
                                @php($uncleared = round($account->current_balance - $account->cleared_balance, 2))
                                @if ($uncleared != 0.0)
                                    {{-- Working balance, so uncleared activity is already counted — say so, otherwise
                                         the difference below looks wrong against what the bank currently reports. --}}
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">includes ${{ $demo->amt($uncleared) }} uncleared</p>
                                @endif
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Difference</p>
                                <p class="mt-0.5 font-mono font-semibold {{ match ($deltaStatus) { 'short' => 'text-red-600 dark:text-red-400', 'over' => 'text-green-600 dark:text-green-400', default => 'text-gray-900 dark:text-gray-100' } }}">
                                    {{ match ($deltaStatus) { 'short' => '−', 'over' => '+', default => '' } }}${{ $demo->amt(abs($delta)) }}
                                </p>
                            </div>
                        </div>

                        @if ($deltaStatus === 'short')
                            <div class="mx-6 mb-4 rounded-md px-3 py-2 text-sm bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-300">
                                This account holds less than its linked envelopes expect — move money in from checking, or double-check recent spend.
                            </div>
                        @elseif ($deltaStatus === 'over')
                            <div class="mx-6 mb-4 rounded-md px-3 py-2 text-sm bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-300">
                                This account holds more than its linked envelopes expect — available to assign to a goal or move to checking.
                            </div>
                        @endif

                        @if ($untaggedThisMonth > 0)
                            <div class="mx-6 mb-4 rounded-md px-3 py-2 text-xs bg-amber-50 dark:bg-amber-900/30 text-amber-800 dark:text-amber-300">
                                {{ $untaggedThisMonth }} withdrawal{{ $untaggedThisMonth === 1 ? '' : 's' }} from this account this month {{ $untaggedThisMonth === 1 ? "wasn't" : "weren't" }} tagged to an envelope.
                            </div>
                        @endif
                    @endif
                </div>
            @endif

            <livewire:transaction-list :account="$account" />

        </div>
    </div>

    <div x-data="{
            close() { $store.reconcile.open = false; $store.reconcile.actualBalance = '' },
            get diff() {
                const a = parseFloat($store.reconcile.actualBalance);
                const c = {{ round($demo->scaleScalar($account->cleared_balance), 2) }};
                if (isNaN(a)) return null;
                return Math.round((a - c) * 100) / 100;
            }
         }"
         x-show="$store.reconcile.open"
         @keydown.escape.window="close()"
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         x-cloak>
        <div class="absolute inset-0 bg-black/50" @click="close()"></div>
        <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md p-6 space-y-5">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Reconcile Account</h3>

            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-500 dark:text-gray-400">Cleared balance</span>
                <span class="font-mono font-semibold text-gray-900 dark:text-gray-100">
                    {{ $account->cleared_balance < 0 ? '−' : '' }}${{ $demo->amt(abs($account->cleared_balance)) }}
                </span>
            </div>

            <form method="POST" action="{{ route('cash-accounts.reconcile', $account) }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Actual balance
                    </label>
                    <input type="number" name="actual_balance" step="0.01" placeholder="0.00" required
                           x-model="$store.reconcile.actualBalance"
                           class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm font-mono" />
                </div>

                <div x-show="diff !== null && diff !== 0" class="rounded-md px-3 py-2 text-sm"
                     :class="diff > 0 ? 'bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-300' : 'bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-300'">
                    Will record a
                    <span class="font-semibold font-mono" x-text="'$' + Math.abs(diff).toFixed(2)"></span>
                    <span x-text="diff > 0 ? 'deposit' : 'withdrawal'"></span>
                    adjustment.
                </div>
                <div x-show="diff === 0" class="rounded-md px-3 py-2 text-sm bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-400">
                    Already balanced — no adjustment needed.
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date</label>
                    <input type="date" name="occurred_at" required value="{{ now()->format('Y-m-d') }}"
                           class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm" />
                </div>

                <div class="flex justify-end gap-3 pt-1">
                    <button type="button" @click="close()"
                            class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                        Cancel
                    </button>
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-600 border border-transparent rounded-md text-sm font-semibold text-white hover:bg-gray-700 dark:hover:bg-gray-500 transition">
                        Confirm
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('reconcile', { open: false, actualBalance: '' });
    });
    </script>
</x-app-layout>

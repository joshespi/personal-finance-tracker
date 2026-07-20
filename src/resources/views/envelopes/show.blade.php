<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    <a href="{{ route('envelopes.index') }}" class="hover:underline">Budget Envelopes</a>
                </p>
                <div class="flex items-center gap-2 mt-0.5">
                    <span class="inline-block w-3 h-3 rounded-full" style="background-color: {{ $envelope->color }}"></span>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ $envelope->name }}</h2>
                </div>
                @if ($envelope->cashAccount)
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                        Lives in <a href="{{ route('cash-accounts.show', $envelope->cashAccount) }}" class="hover:underline">{{ $envelope->cashAccount->name }}</a>
                    </p>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('envelopes.edit', $envelope) }}"
                   class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                    Edit
                </a>
                <form method="POST" action="{{ route('envelopes.destroy', $envelope) }}"
                      onsubmit="return confirm('Delete this envelope and all its transactions?')">
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

            @php
                $hasMonthlyTarget = $envelope->monthly_target !== null && (float)$envelope->monthly_target > 0;
                $hasGoal          = $envelope->goal_amount !== null && (float)$envelope->goal_amount > 0;
            @endphp
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <x-stat-tile>
                    <x-slot:label>Current Balance</x-slot:label>
                    <p class="mt-1 text-3xl font-semibold font-mono {{ $envelope->current_balance >= 0 ? 'text-gray-900 dark:text-gray-100' : 'text-red-600' }}">
                        {{ $envelope->current_balance < 0 ? '−' : '' }}${{ $demo->amt(abs($envelope->current_balance)) }}
                    </p>
                </x-stat-tile>

                @if ($hasMonthlyTarget)
                    @php
                        $target = (float) $envelope->monthly_target;
                        $spent  = (float) $envelope->spent_this_month;
                        $pct    = min(100, round($spent / $target * 100));
                    @endphp
                    <x-stat-tile>
                        <x-slot:label>Spent This Month</x-slot:label>
                        <p class="mt-1 text-2xl font-semibold font-mono {{ $spent > $target ? 'text-red-600' : 'text-gray-900 dark:text-gray-100' }}">
                            ${{ $demo->amt($spent) }} <span class="text-sm text-gray-400">/ ${{ $demo->amt($target) }}</span>
                        </p>
                        <div class="mt-2 w-full bg-gray-100 dark:bg-gray-700 rounded-full h-1.5 overflow-hidden">
                            <div class="h-full rounded-full transition-all"
                                 style="width: {{ $pct }}%; background-color: {{ $spent > $target ? '#dc2626' : $envelope->color }};"></div>
                        </div>
                    </x-stat-tile>
                @endif

                @if ($hasGoal)
                    @php
                        $goalAmount   = (float) $envelope->goal_amount;
                        $balance      = $envelope->current_balance;
                        $goalPct      = min(100, $goalAmount > 0 ? round($balance / $goalAmount * 100) : 0);
                        $remaining    = max(0, $goalAmount - $balance);
                        $goalDate     = $envelope->goal_date;
                        $monthsLeft   = $goalDate ? (int) ceil(now()->floatDiffInMonths($goalDate)) : null;
                        $monthsLeft   = ($monthsLeft !== null && $monthsLeft > 0) ? $monthsLeft : null;
                        $suggestedPmo = ($monthsLeft && $remaining > 0) ? round($remaining / $monthsLeft, 2) : null;
                    @endphp
                    <x-stat-tile>
                        <x-slot:label>
                            Savings Goal{{ $goalDate ? ' · by ' . $goalDate->format('M Y') : '' }}
                        </x-slot:label>
                        <p class="mt-1 text-2xl font-semibold font-mono text-gray-900 dark:text-gray-100">
                            ${{ $demo->amt($balance) }} <span class="text-sm text-gray-400">/ ${{ $demo->amt($goalAmount) }}</span>
                        </p>
                        <div class="mt-2 w-full bg-gray-100 dark:bg-gray-700 rounded-full h-1.5 overflow-hidden">
                            <div class="h-full rounded-full transition-all"
                                 style="width: {{ $goalPct }}%; background-color: {{ $envelope->color }};"></div>
                        </div>
                        @if ($suggestedPmo)
                            <p class="mt-1.5 text-xs text-gray-400 dark:text-gray-500">~${{ $demo->amt($suggestedPmo) }}/mo suggested · {{ $monthsLeft }} months left</p>
                        @elseif ($monthsLeft !== null)
                            <p class="mt-1.5 text-xs text-green-600 dark:text-green-400">Goal reached!</p>
                        @endif
                    </x-stat-tile>
                @endif
            </div>

            @if ($envelope->notes)
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5">
                    <h3 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Notes</h3>
                    <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap break-words">{{ $envelope->notes }}</p>
                </div>
            @endif

            {{-- Add Transaction --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Record Transaction</h3>
                </div>
                <div class="p-6">
                    <form method="POST" action="{{ route('envelopes.transactions.store', $envelope) }}"
                          class="flex flex-wrap items-end gap-4">
                        @csrf
                        <input type="hidden" name="type" value="fund">

                        <div>
                            <x-input-label for="amount" value="Amount" />
                            <x-text-input id="amount" name="amount" type="number" class="mt-1 block w-40"
                                          :value="old('amount')" required min="0" step="any" placeholder="0.00" />
                            <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="occurred_at" value="Date" />
                            <x-text-input id="occurred_at" name="occurred_at" type="date" class="mt-1 block w-40"
                                          :value="old('occurred_at', now()->format('Y-m-d'))" required />
                            <x-input-error :messages="$errors->get('occurred_at')" class="mt-2" />
                        </div>

                        @if ($cashAccounts->isNotEmpty())
                            <div>
                                <x-input-label for="cash_account_id" value="From spending account (optional)" />
                                <select id="cash_account_id" name="cash_account_id"
                                        class="mt-1 block w-48 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">— None —</option>
                                    @foreach ($cashAccounts as $ca)
                                        <option value="{{ $ca->id }}" @selected((string)old('cash_account_id') === (string)$ca->id)>{{ $ca->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('cash_account_id')" class="mt-2" />
                            </div>
                        @endif

                        <div class="flex-1 min-w-48">
                            <x-input-label for="description" value="Description (optional)" />
                            <x-text-input id="description" name="description" type="text" class="mt-1 block w-full"
                                          :value="old('description')" maxlength="500" placeholder="Costco, allowance…" />
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>

                        <div class="pb-0.5">
                            <x-primary-button>Record</x-primary-button>
                        </div>
                    </form>
                    @if ($cashAccounts->isNotEmpty())
                        <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">Choosing a spending account will create a paired withdrawal there.</p>
                    @endif
                </div>
            </div>

            {{-- Transaction History --}}
            @php
                $allRows = $envelope->transactions->map(fn ($t) => [
                    'date'        => $t->occurred_at,
                    'type'        => 'fund',
                    'description' => $t->description,
                    'amount'      => (float) $t->amount,
                    'source'      => null,
                    'delete_url'  => route('envelopes.transactions.destroy', $t),
                ])->concat($envelope->spendTransactions->map(fn ($t) => [
                    'date'        => $t->occurred_at,
                    'type'        => 'spend',
                    'description' => $t->description,
                    'amount'      => (float) $t->amount,
                    'source'      => $t->cashAccount?->name,
                    'delete_url'  => route('cash-accounts.transactions.destroy', $t),
                ]))->sortByDesc(fn ($r) => $r['date']->timestamp)->values();
            @endphp
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Transactions</h3>
                </div>

                @if ($allRows->isEmpty())
                    <div class="p-6 text-sm text-gray-500 dark:text-gray-400">No transactions yet.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Account</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Outflow</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Inflow</th>
                                    <th class="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($allRows as $row)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-6 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $row['date']->format('M j, Y') }}</td>
                                        <td class="px-6 py-3">
                                            @if ($row['type'] === 'fund')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300">Fund</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300">Spend</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-3 text-gray-500 dark:text-gray-400">{{ $row['description'] ?? '—' }}</td>
                                        <td class="px-6 py-3 text-gray-400 dark:text-gray-500 text-xs">{{ $row['source'] ?? '—' }}</td>
                                        <x-flow-cells :inflow="$row['type'] === 'fund'" :amount="$demo->amt((float) $row['amount'])" />
                                        <td class="px-6 py-3 text-right">
                                            <form method="POST" action="{{ $row['delete_url'] }}" class="inline"
                                                  onsubmit="return confirm('Delete this transaction?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 dark:text-red-400 hover:underline text-xs">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

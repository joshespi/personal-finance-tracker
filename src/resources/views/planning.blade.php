<x-app-layout>
    @push('head-vite')
        @vite(['resources/js/chartjs.js', 'resources/js/planning-charts.js', 'resources/js/forecast-charts.js'])
    @endpush

    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Money</p>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    @if ($tab === 'debt-payoff')
                        Debt Payoff
                    @elseif ($tab === 'allocator')
                        Extra-Cash Allocator
                    @elseif ($tab === 'retirement')
                        {{ $mode === 'trajectory' ? 'FIRE Forecast' : 'Retirement Catch-Up' }}
                    @else
                        Emergency Fund
                    @endif
                </h2>
            </div>

            <x-tab-nav :tabs="[
                ['href' => route('planning', ['tab' => 'debt-payoff']), 'label' => 'Debt Payoff', 'active' => $tab === 'debt-payoff'],
                ['href' => route('planning', ['tab' => 'allocator']), 'label' => 'Allocator', 'active' => $tab === 'allocator'],
                ['href' => route('planning', ['tab' => 'emergency-fund']), 'label' => 'Emergency Fund', 'active' => $tab === 'emergency-fund'],
                ['href' => route('planning', ['tab' => 'retirement']), 'label' => 'Retirement', 'active' => $tab === 'retirement'],
            ]" />
        </div>
    </x-slot>

    <div class="py-12">

        {{-- ═══════════════════════════════ DEBT PAYOFF TAB ═══════════════════════════════ --}}
        @if ($tab === 'debt-payoff')
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @if (! $data['has_data'])
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-sm text-gray-600 dark:text-gray-400">
                    <p class="font-semibold text-gray-800 dark:text-gray-200 mb-2">No active debt found.</p>
                    <p>Add liabilities with a current balance to see your payoff plan.</p>
                    <a href="{{ route('liabilities.index') }}" class="inline-block mt-3 text-indigo-600 dark:text-indigo-400 hover:underline">
                        Go to Liabilities &rarr;
                    </a>
                </div>
            @else
                @php
                    $debts     = $data['debts'];
                    $mortgages = $data['mortgages'];
                    $snowball  = $data['snowball'];
                    $avalanche = $data['avalanche'];
                @endphp

                @if ($data['negative_amortization_count'] > 0)
                    <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-600 rounded-lg px-5 py-4 text-sm text-amber-800 dark:text-amber-200">
                        <strong>Warning:</strong> {{ $data['negative_amortization_count'] }} {{ Str::plural('debt', $data['negative_amortization_count']) }}
                        have a minimum payment lower than the monthly interest — the balance is growing. Increase those payments to make progress.
                    </div>
                @endif

                @if (count(array_filter($debts, fn($d) => ! $d['min_payment_set'])) > 0)
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-300 dark:border-blue-600 rounded-lg px-5 py-4 text-sm text-blue-800 dark:text-blue-200">
                        Some debts don't have a minimum payment set — using a 2% estimate. Edit the liability to enter the exact amount for a more accurate plan.
                    </div>
                @endif

                {{-- Alpine.data('debtPayoff', ...) is registered by planning-charts.js (pushed
                     to head-vite above) — it owns the chart setup and the "extra payment"
                     what-if slider, which now re-simulates via the server (DebtPayoffService)
                     instead of a second client-side copy of the payoff algorithm. --}}
                <div
                    x-data="debtPayoff(@js(array_values($debts)), @js($snowball), @js($avalanche))"
                    class="space-y-8"
                >
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <x-stat-tile>
                            <x-slot:label>Total debt</x-slot:label>
                            <p class="mt-1 text-2xl font-semibold font-mono text-gray-800 dark:text-gray-100">${{ $demo->amt($data['total_balance'], 0) }}</p>
                            <x-slot:caption>high-interest only</x-slot:caption>
                        </x-stat-tile>
                        <x-stat-tile>
                            <x-slot:label>Interest / month</x-slot:label>
                            <p class="mt-1 text-2xl font-semibold font-mono text-rose-600 dark:text-rose-400">${{ $demo->amt($data['total_monthly_interest'], 0) }}</p>
                            <x-slot:caption>given away to lenders</x-slot:caption>
                        </x-stat-tile>
                        <x-stat-tile>
                            <x-slot:label>Interest / year</x-slot:label>
                            <p class="mt-1 text-2xl font-semibold font-mono text-rose-600 dark:text-rose-400">${{ $demo->amt($data['yearly_interest'], 0) }}</p>
                            <x-slot:caption>at current balances</x-slot:caption>
                        </x-stat-tile>
                        <x-stat-tile>
                            <x-slot:label>Payoff (min only)</x-slot:label>
                            <p class="mt-1 text-2xl font-semibold font-mono text-gray-800 dark:text-gray-100">
                                @if ($snowball['months'] >= 600)
                                    50+ yrs
                                @else
                                    {{ floor($snowball['months'] / 12) > 0 ? floor($snowball['months'] / 12) . 'yr ' : '' }}{{ $snowball['months'] % 12 > 0 ? ($snowball['months'] % 12) . 'mo' : '' }}
                                @endif
                            </p>
                            @if ($snowball['months'] < 600)
                                <x-slot:caption>{{ \Carbon\Carbon::now()->addMonths($snowball['months'])->format('M Y') }}</x-slot:caption>
                            @endif
                        </x-stat-tile>
                    </div>

                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">Extra monthly payment</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Slide to see how extra money accelerates your payoff</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-gray-500 dark:text-gray-400 text-sm">$</span>
                                <input type="number" min="0" max="10000" step="25" x-model="extraPayment"
                                       class="w-28 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm text-right font-mono" />
                            </div>
                        </div>
                        <input type="range" min="0" max="2000" step="25" x-model="extraPayment"
                               class="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-lg appearance-none cursor-pointer accent-indigo-600" />
                        <div class="flex justify-between text-xs text-gray-400 dark:text-gray-500 mt-1">
                            <span>$0</span><span>$500</span><span>$1,000</span><span>$1,500</span><span>$2,000</span>
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <button type="button" @click="strategy = 'snowball'"
                                :class="strategy === 'snowball' ? 'ring-2 ring-indigo-500' : 'opacity-80 hover:opacity-100'"
                                class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5 text-left transition">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="inline-block w-3 h-3 rounded-full bg-indigo-500"></span>
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">Snowball</p>
                                <span class="text-xs text-gray-400 dark:text-gray-500 ml-auto">lowest balance first</span>
                            </div>
                            <p class="text-2xl font-semibold font-mono text-indigo-600 dark:text-indigo-400" x-text="formatMonths(snowballResult.months)"></p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5" x-text="payoffDate(snowballResult.months)"></p>
                            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                                Total interest: <span class="font-mono font-semibold" x-text="'$' + Math.round(snowballResult.total_interest).toLocaleString()"></span>
                            </p>
                        </button>
                        <button type="button" @click="strategy = 'avalanche'"
                                :class="strategy === 'avalanche' ? 'ring-2 ring-emerald-500' : 'opacity-80 hover:opacity-100'"
                                class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5 text-left transition">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="inline-block w-3 h-3 rounded-full bg-emerald-500"></span>
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">Avalanche</p>
                                <span class="text-xs text-gray-400 dark:text-gray-500 ml-auto">highest rate first</span>
                            </div>
                            <p class="text-2xl font-semibold font-mono text-emerald-600 dark:text-emerald-400" x-text="formatMonths(avalancheResult.months)"></p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5" x-text="payoffDate(avalancheResult.months)"></p>
                            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                                Total interest: <span class="font-mono font-semibold" x-text="'$' + Math.round(avalancheResult.total_interest).toLocaleString()"></span>
                            </p>
                        </button>
                    </div>

                    <template x-if="avalancheResult.total_interest < snowballResult.total_interest">
                        <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-300 dark:border-emerald-700 rounded-lg px-5 py-3 text-sm text-emerald-800 dark:text-emerald-200">
                            Avalanche saves you <strong x-text="'$' + Math.round(snowballResult.total_interest - avalancheResult.total_interest).toLocaleString()"></strong> in interest
                            and finishes <strong x-text="Math.abs(snowballResult.months - avalancheResult.months) + ' months'"></strong> faster.
                        </div>
                    </template>

                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">Remaining balance over time</p>
                        <div class="h-64"><canvas id="debtChart"></canvas></div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">High-interest debts</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-900/40">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Debt</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Balance</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">APR</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Interest/mo</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Min pmt</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Snowball</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Avalanche</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach ($debts as $d)
                                        @php
                                            $debtId  = $d['id'];
                                            // Credit-card debt rows are synthesized from a CashAccount, not a real
                                            // Liability — see User::creditCardDebts() — so they route differently.
                                            $showUrl = $d['source'] === 'cash_account'
                                                ? route('cash-accounts.show', $d['entity_id'])
                                                : route('liabilities.show', $d['entity_id']);
                                        @endphp
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                            <td class="px-4 py-3">
                                                <a href="{{ $showUrl }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">{{ $d['name'] }}</a>
                                                @if ($d['source'] === 'cash_account') <span class="ml-1 text-xs text-gray-400 dark:text-gray-500" title="Tracked as a cash account, not a Liability">card</span> @endif
                                                @if ($d['negative_amortization']) <span class="ml-1 text-xs text-amber-600 dark:text-amber-400" title="Minimum payment is less than monthly interest">⚠</span> @endif
                                                @if (! $d['min_payment_set']) <span class="ml-1 text-xs text-blue-500 dark:text-blue-400" title="Estimated minimum payment">est.</span> @endif
                                            </td>
                                            <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-gray-300">${{ $demo->amt($d['balance'], 0) }}</td>
                                            <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-gray-300">{{ $d['apr'] > 0 ? number_format($d['apr'], 2) . '%' : '—' }}</td>
                                            <td class="px-4 py-3 text-right font-mono {{ $d['negative_amortization'] ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400' }}">${{ $demo->amt($d['monthly_interest'], 0) }}</td>
                                            <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-gray-300">${{ $demo->amt($d['min_payment'], 0) }}</td>
                                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400" x-text="snowballResult.payoff_per_debt['{{ $debtId }}'] ? payoffDate(snowballResult.payoff_per_debt['{{ $debtId }}']) : '—'"></td>
                                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400" x-text="avalancheResult.payoff_per_debt['{{ $debtId }}'] ? payoffDate(avalancheResult.payoff_per_debt['{{ $debtId }}']) : '—'"></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                @if (count($mortgages) > 0)
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">Long-term financing</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Mortgages are excluded from payoff strategies — they're managed separately.</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-900/40">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Loan</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Balance</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">APR</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Interest/mo</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Min pmt</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach ($mortgages as $m)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                            <td class="px-4 py-3"><a href="{{ route('liabilities.show', $m['id']) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">{{ $m['name'] }}</a></td>
                                            <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-gray-300">${{ $demo->amt($m['balance'], 0) }}</td>
                                            <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-gray-300">{{ $m['apr'] > 0 ? number_format($m['apr'], 2) . '%' : '—' }}</td>
                                            <td class="px-4 py-3 text-right font-mono text-gray-500 dark:text-gray-400">${{ $demo->amt($m['monthly_interest'], 0) }}</td>
                                            <td class="px-4 py-3 text-right font-mono text-gray-500 dark:text-gray-400">{{ $m['min_payment'] !== null ? '$' . $demo->amt($m['min_payment'], 0) : '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

            @endif
        </div>

        {{-- ═══════════════════════════════ ALLOCATOR TAB ═══════════════════════════════ --}}
        @elseif ($tab === 'allocator')
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <form method="GET" action="{{ route('planning', ['tab' => 'allocator']) }}" class="flex items-end gap-3">
                    <div class="flex-1">
                        <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Extra cash to allocate</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-3 flex items-center text-gray-500 dark:text-gray-400 pointer-events-none">$</span>
                            <input id="amount" name="amount" type="number" min="1" max="10000000" step="0.01"
                                   value="{{ $amount ?? old('amount') }}" placeholder="500.00"
                                   class="block w-full pl-7 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                        </div>
                        @error('amount')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <x-primary-button type="submit">Allocate</x-primary-button>
                </form>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    Ranked recommendation: emergency fund → high-APR debt → savings goals → remainder.
                </p>
            </div>

            @if ($amount !== null)
                @if (empty($buckets))
                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg px-5 py-4 text-sm text-green-800 dark:text-green-300">
                        <p class="font-semibold">Nothing to allocate to — looking good.</p>
                        <p class="mt-1">Emergency fund is on track, no revolving debt, and all savings goals are funded. Consider investing the full
                            <span class="font-mono font-bold">${{ $demo->amt($amount) }}</span>.</p>
                    </div>
                @else
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                        <div class="divide-y divide-gray-100 dark:divide-gray-700">
                            @php $rank = 1; @endphp
                            @foreach ($buckets as $bucket)
                                @php
                                    $colors = match ($bucket['type']) {
                                        'emergency' => ['dot' => 'bg-emerald-500', 'badge' => 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300'],
                                        'debt'      => ['dot' => 'bg-red-500',     'badge' => 'bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300'],
                                        default     => ['dot' => 'bg-indigo-500',  'badge' => 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-800 dark:text-indigo-300'],
                                    };
                                    $fully = $bucket['amount'] >= $bucket['gap'];
                                @endphp
                                <div class="px-6 py-4 flex items-start gap-4">
                                    <div class="flex-shrink-0 w-6 h-6 rounded-full {{ $colors['dot'] }} flex items-center justify-center text-white text-xs font-bold mt-0.5">{{ $rank++ }}</div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <p class="font-semibold text-gray-900 dark:text-gray-100 truncate">{{ $bucket['label'] }}</p>
                                            <span class="text-xs px-2 py-0.5 rounded-full {{ $colors['badge'] }}">{{ ucfirst($bucket['type']) }}</span>
                                            @if ($fully) <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400">Fully covered</span> @endif
                                        </div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $bucket['reason'] }}</p>
                                        @if (! $fully)
                                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">${{ $demo->amt($bucket['gap'] - $bucket['amount']) }} still needed after this allocation</p>
                                        @endif
                                    </div>
                                    <div class="flex-shrink-0 text-right">
                                        <p class="font-mono font-bold text-lg text-gray-900 dark:text-gray-100">${{ $demo->amt($bucket['amount']) }}</p>
                                    </div>
                                </div>
                            @endforeach

                            @if ($remainder > 0)
                                <div class="px-6 py-4 flex items-start gap-4 bg-indigo-50 dark:bg-indigo-900/20">
                                    <div class="flex-shrink-0 w-6 h-6 rounded-full bg-gray-400 dark:bg-gray-500 flex items-center justify-center text-white text-xs font-bold mt-0.5">&mdash;</div>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold text-gray-900 dark:text-gray-100">Remainder — invest or discretionary</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">No further priority buckets. Consider investing in a brokerage or Roth IRA.</p>
                                    </div>
                                    <div class="flex-shrink-0 text-right">
                                        <p class="font-mono font-bold text-lg text-gray-900 dark:text-gray-100">${{ $demo->amt($remainder) }}</p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                    <p class="text-right text-xs text-gray-400 dark:text-gray-500">Total: ${{ $demo->amt($amount) }}</p>
                @endif
            @endif

        </div>

        {{-- ═══════════════════════════════ EMERGENCY FUND TAB ═══════════════════════════════ --}}
        @elseif ($tab === 'emergency-fund')
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @if ($mandatoryEnvelopes->isEmpty() || $monthlyBaseline == 0)
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-sm text-gray-600 dark:text-gray-400 space-y-2">
                    <p class="font-semibold text-gray-800 dark:text-gray-200">No mandatory expenses configured.</p>
                    <p>Mark envelopes as a <strong>Necessity</strong>, or check <strong>Include in emergency-fund target</strong>, in their edit form to build your target. Typical candidates: rent, utilities, groceries, fuel, insurance.</p>
                    <a href="{{ route('envelopes.index') }}" class="inline-block mt-2 text-indigo-600 dark:text-indigo-400 hover:underline">Go to envelopes &rarr;</a>
                </div>
            @else
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <x-stat-tile>
                        <x-slot:label>Monthly baseline</x-slot:label>
                        <p class="mt-1 text-2xl font-semibold font-mono text-gray-800 dark:text-gray-100">${{ $demo->amt($monthlyBaseline) }}</p>
                        <x-slot:caption>avg last 6 months</x-slot:caption>
                    </x-stat-tile>
                    <x-stat-tile>
                        <x-slot:label>3-month target</x-slot:label>
                        <p class="mt-1 text-2xl font-semibold font-mono text-gray-800 dark:text-gray-100">${{ $demo->amt($target3) }}</p>
                    </x-stat-tile>
                    <x-stat-tile>
                        <x-slot:label>6-month target</x-slot:label>
                        <p class="mt-1 text-2xl font-semibold font-mono text-gray-800 dark:text-gray-100">${{ $demo->amt($target6) }}</p>
                    </x-stat-tile>
                    <x-stat-tile>
                        <x-slot:label>You have</x-slot:label>
                        @if ($currentSavings !== null)
                            <p class="mt-1 text-2xl font-semibold font-mono {{ $currentSavings >= $target3 ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }}">${{ $demo->amt($currentSavings) }}</p>
                            <x-slot:caption>{{ $emergencyEnvelope->name }}</x-slot:caption>
                        @else
                            <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">—</p>
                            <a href="{{ route('envelopes.index') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Set up &rarr;</a>
                        @endif
                    </x-stat-tile>
                </div>

                @if (!empty($bars))
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5 space-y-5">
                        @foreach ($bars as $bar)
                            <div>
                                <div class="flex justify-between text-sm mb-1.5">
                                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ $bar['label'] }}</span>
                                    <span class="font-mono text-gray-600 dark:text-gray-400">
                                        ${{ $demo->amt($currentSavings) }} / ${{ $demo->amt($bar['target']) }}
                                        <span class="text-gray-400 dark:text-gray-500 ml-1">({{ $bar['pct'] }}%)</span>
                                    </span>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2.5 overflow-hidden">
                                    <div class="h-full rounded-full transition-all {{ $currentSavings >= $bar['target'] ? 'bg-green-500' : 'bg-indigo-500' }}"
                                         style="width: {{ $bar['pct'] }}%"></div>
                                </div>
                                @if ($currentSavings < $bar['target'])
                                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">${{ $demo->amt($bar['target'] - $currentSavings) }} to go</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Mandatory Expenses</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Average monthly spend over the last 6 months</p>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($monthlyBreakdown as $row)
                            <div class="px-6 py-3 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="inline-block w-2.5 h-2.5 rounded-full shrink-0" style="background-color: {{ $row['envelope']->color }}"></span>
                                    <a href="{{ route('envelopes.show', $row['envelope']) }}" class="text-sm text-gray-800 dark:text-gray-200 hover:underline">{{ $row['envelope']->name }}</a>
                                </div>
                                <span class="text-sm font-mono text-gray-700 dark:text-gray-300">${{ $demo->amt($row['avg']) }}/mo</span>
                            </div>
                        @endforeach
                        <div class="px-6 py-3 flex items-center justify-between bg-gray-50 dark:bg-gray-700/40">
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Total</span>
                            <span class="text-sm font-mono font-semibold text-gray-800 dark:text-gray-200">${{ $demo->amt($monthlyBaseline) }}/mo</span>
                        </div>
                    </div>
                </div>
            @endif

            @if ($mandatoryEnvelopes->isNotEmpty() && $emergencyEnvelope === null)
                <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg px-5 py-4 text-sm text-amber-800 dark:text-amber-300">
                    No envelope is marked as your emergency fund savings. Edit an envelope and check <strong>Emergency fund savings</strong> so the calculator can show your progress.
                </div>
            @endif

        </div>
        @endif

        @if ($tab === 'retirement')
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Two lenses on "will my money be enough": an age-based 4%-rule target,
                 and the FIRE Forecast wealth trajectory (formerly the standalone
                 /forecast page). Each keeps its own starting-value default — portfolio
                 value for the target, full net worth for the trajectory — since those
                 are different questions, not two copies of the same one. --}}
            <x-tab-nav :tabs="[
                ['href' => route('planning', ['tab' => 'retirement', 'mode' => 'target']), 'label' => 'Age-Based Target', 'active' => $mode === 'target'],
                ['href' => route('planning', ['tab' => 'retirement', 'mode' => 'trajectory']), 'label' => 'FIRE Trajectory', 'active' => $mode === 'trajectory'],
            ]" />

        @if ($mode === 'target')

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-5 text-sm text-gray-600 dark:text-gray-400 space-y-1.5">
                <p class="text-gray-700 dark:text-gray-300">
                    Enter your birth year and current contribution to see whether you're on track for retirement — and what monthly catch-up looks like if you're not.
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Birth year is optional and never stored — it's used only to compute years to retirement for this projection.
                    Portfolio value is pre-filled from your latest snapshots.
                </p>
            </div>

            <form method="GET" action="{{ route('planning', ['tab' => 'retirement']) }}"
                  class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-5 space-y-5">
                <input type="hidden" name="tab" value="retirement">
                <input type="hidden" name="mode" value="target">

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-5 gap-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Birth Year <span class="text-gray-400 font-normal">(optional)</span>
                        </label>
                        <input type="number" name="birth_year" value="{{ $birthYear ?? '' }}"
                               min="1930" max="{{ now()->year - 18 }}" placeholder="{{ now()->year - 35 }}"
                               class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Retire at Age</label>
                        <input type="number" name="retirement_age" value="{{ $retirementAge }}"
                               min="40" max="90"
                               class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Expected Return</label>
                        <div class="relative">
                            <input type="number" name="annual_return" value="{{ $annualReturn }}" step="0.1" min="0" max="30"
                                   class="pr-7 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" />
                            <span class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 text-sm">%</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Current Portfolio Value
                            @if ($currentValue == $defaultValue && $defaultValue > 0)
                                <span class="text-gray-400 font-normal">(from your data)</span>
                            @endif
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 text-sm">$</span>
                            <input type="number" name="current_value" value="{{ $currentValue }}" step="any" min="0"
                                   class="pl-7 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Monthly Contribution</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 text-sm">$</span>
                            <input type="number" name="monthly_contrib" value="{{ $monthlyContrib }}" step="any" min="0"
                                   class="pl-7 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1"
                               title="Used for the 4% rule target: 25x your annual expenses = the amount needed to retire. If left blank, your app's recorded income is used.">
                            Annual Expenses <span class="text-gray-400 font-normal">(optional ⓘ)</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 text-sm">$</span>
                            <input type="number" name="annual_expenses" value="{{ $annualExpenses ?? '' }}" step="any" min="0"
                                   placeholder="{{ $annualIncome > 0 ? $demo->amt($annualIncome, 0) : '' }}"
                                   class="pl-7 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" />
                        </div>
                        @if ($annualIncome > 0 && ! $annualExpenses)
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Using recorded income: ${{ $demo->amt($annualIncome, 0) }}/yr</p>
                        @endif
                    </div>
                </div>

                <div>
                    <button type="submit"
                            class="px-4 py-2 bg-gray-800 dark:bg-gray-700 text-white text-sm font-semibold rounded-md hover:bg-gray-700 dark:hover:bg-gray-600 transition">
                        Calculate
                    </button>
                </div>
            </form>

            @if ($result !== null)

                {{-- Summary cards --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <x-stat-tile>
                        <x-slot:label>Years to Retirement</x-slot:label>
                        <p class="mt-1 text-2xl font-semibold font-mono text-gray-800 dark:text-gray-100">{{ $result['years_left'] }}</p>
                        <x-slot:caption>Retire at {{ $retirementAge }}, currently {{ $age }}</x-slot:caption>
                    </x-stat-tile>

                    <x-stat-tile>
                        <x-slot:label>Projected at Retirement</x-slot:label>
                        <p class="mt-1 text-2xl font-semibold font-mono text-gray-800 dark:text-gray-100">${{ $demo->amt($result['projected_fv'], 0) }}</p>
                        <x-slot:caption>at {{ $annualReturn }}% annual return</x-slot:caption>
                    </x-stat-tile>

                    @if ($result['target'] !== null)
                        <x-stat-tile>
                            <x-slot:label title="25× your annual expenses/income — the amount needed to withdraw 4% per year indefinitely">Target (4% Rule ⓘ)</x-slot:label>
                            <p class="mt-1 text-2xl font-semibold font-mono {{ $result['on_track'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                                ${{ $demo->amt($result['target'], 0) }}
                            </p>
                            <x-slot:caption>
                                <span class="{{ $result['on_track'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                                    {{ $result['on_track'] ? 'On track' : '$' . $demo->amt(abs($result['gap']), 0) . ' short' }}
                                </span>
                            </x-slot:caption>
                        </x-stat-tile>
                    @endif
                </div>

                {{-- Catch-up callout --}}
                @if ($result['target'] !== null && ! $result['on_track'] && $result['required_contrib'] !== null)
                    <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg px-5 py-4 space-y-1">
                        <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Catch-up needed</p>
                        <p class="text-sm text-amber-700 dark:text-amber-400">
                            To reach your target by {{ $retirementAge }}, contribute
                            <strong>${{ $demo->amt($result['required_contrib'], 0) }}/month</strong>
                            (you're currently contributing ${{ $demo->amt($monthlyContrib, 0) }}/month —
                            {{ $result['required_contrib'] > $monthlyContrib
                                ? 'an increase of $' . $demo->amt($result['required_contrib'] - $monthlyContrib, 0) . '/month'
                                : 'already enough' }}).
                        </p>
                    </div>
                @elseif ($result['target'] !== null && $result['on_track'])
                    <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 rounded-lg px-5 py-4">
                        <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-300">You're on track.</p>
                        <p class="text-sm text-emerald-700 dark:text-emerald-400 mt-0.5">
                            Your projected value at {{ $retirementAge }} exceeds the 4% rule target by ${{ $demo->amt(abs($result['gap']), 0) }}.
                        </p>
                    </div>
                @endif

                {{-- Fidelity benchmarks --}}
                @if (! empty($result['benchmarks']))
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700">
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Fidelity Milestones</h3>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                Fidelity recommends saving a multiple of your annual income by each age. Based on ${{ $demo->amt($annualIncome, 0) }}/yr.
                            </p>
                        </div>
                        <div class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($result['benchmarks'] as $b)
                                <div class="px-5 py-3 flex items-center justify-between">
                                    <div>
                                        <span class="text-sm font-medium text-gray-800 dark:text-gray-200">Age {{ $b['age'] }}</span>
                                        <span class="ml-2 text-xs text-gray-400 dark:text-gray-500">{{ $b['multiple'] }}× income</span>
                                    </div>
                                    <div class="flex items-center gap-4 text-sm">
                                        <div class="text-right">
                                            <p class="text-xs text-gray-400 dark:text-gray-500">Target</p>
                                            <p class="font-mono text-gray-700 dark:text-gray-300">${{ $demo->amt($b['target'], 0) }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-xs text-gray-400 dark:text-gray-500">Projected</p>
                                            <p class="font-mono {{ $b['on_track'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                                                ${{ $demo->amt($b['projected'], 0) }}
                                            </p>
                                        </div>
                                        <span class="text-xs font-semibold {{ $b['on_track'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                                            {{ $b['on_track'] ? '✓' : '↑' }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @elseif ($result !== null && $annualIncome <= 0)
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4 text-sm text-gray-500 dark:text-gray-400">
                        Fidelity milestones require income data. Add deposits to your cash accounts so the app can estimate your income, or enter annual expenses above.
                    </div>
                @endif

            @elseif ($age !== null && $age >= $retirementAge)
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                    Retirement age must be greater than current age.
                </div>
            @endif

        @else {{-- mode === 'trajectory': FIRE Forecast, formerly the standalone /forecast page --}}

            <form method="GET" action="{{ route('planning', ['tab' => 'retirement']) }}" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 space-y-5">
                <input type="hidden" name="tab" value="retirement">
                <input type="hidden" name="mode" value="trajectory">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Starting Net Worth
                            @if ($startingNw == $defaultStartNw)
                                <span class="text-gray-400 font-normal">(from your data)</span>
                            @endif
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 dark:text-gray-400 text-sm">$</span>
                            <x-masked-money-input name="starting_nw" :value="$startingNw" :masked="$demo->isActive()" step="any" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Monthly Savings
                            @if ($monthlySavings == $defaultMonthlySavings)
                                <span class="text-gray-400 font-normal">(3-mo avg)</span>
                            @endif
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 dark:text-gray-400 text-sm">$</span>
                            <x-masked-money-input name="monthly_savings" :value="$monthlySavings" :masked="$demo->isActive()" step="any" min="0" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">FIRE Target <span class="text-gray-400 font-normal">(optional)</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 dark:text-gray-400 text-sm">$</span>
                            <x-masked-money-input name="fire_target" :value="$fireTarget ?? ''" :masked="$demo->isActive() && $fireTarget !== null" step="any" min="0" placeholder="1000000" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Annual Return</label>
                        <div class="relative">
                            <input type="number" name="annual_return" value="{{ $annualReturn }}" step="0.1" min="0" max="30"
                                   class="pr-8 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" />
                            <span class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 dark:text-gray-400 text-sm">%</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Inflation Rate</label>
                        <div class="relative">
                            <input type="number" name="inflation_rate" value="{{ $inflationRate }}" step="0.1" min="0" max="20"
                                   class="pr-8 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" />
                            <span class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 dark:text-gray-400 text-sm">%</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Time Horizon</label>
                        <div class="flex gap-1.5">
                            @foreach ([10, 20, 30, 40, 50] as $y)
                                <button type="submit" name="years" value="{{ $y }}"
                                        class="flex-1 py-1.5 rounded-md text-sm font-medium border transition
                                            {{ $years === $y
                                                ? 'bg-indigo-600 border-indigo-600 text-white'
                                                : 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-indigo-400' }}">
                                    {{ $y }}yr
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 transition">
                        Update Projection
                    </button>
                    <span class="text-xs text-gray-400 dark:text-gray-500">Starting net worth and monthly savings are pre-filled from your data.</span>
                </div>
            </form>

            @if ($fireTarget !== null)
                @php
                    $endNominal = $projection[count($projection) - 1]['nominal'];
                    $shortfall  = max(0, $fireTarget - $endNominal);
                @endphp
                <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-700 rounded-xl p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500 dark:text-indigo-400">FIRE Target: ${{ $demo->amt($fireTarget, 0) }}</p>
                    @if ($fireMilestone)
                        <p class="text-2xl font-bold text-indigo-900 dark:text-indigo-100 mt-1">
                            Year {{ $fireMilestone['year'] }} &mdash; {{ $fireMilestone['calendar'] }}
                        </p>
                        <p class="text-sm text-indigo-600 dark:text-indigo-400 mt-0.5">
                            {{ $fireMilestone['year'] === 0 ? 'Already achieved!' : 'In ' . $fireMilestone['year'] . ' ' . Str::plural('year', $fireMilestone['year']) }}
                        </p>
                    @else
                        <p class="text-xl font-bold text-indigo-900 dark:text-indigo-100 mt-1">Not reached in {{ $years }} years</p>
                        <p class="text-sm text-indigo-600 dark:text-indigo-400 mt-0.5">
                            ${{ $demo->amt($shortfall, 0) }} short at year {{ $years }} &mdash; try increasing savings or return rate
                        </p>
                    @endif
                </div>
            @endif

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                @foreach ($standardMilestones as $m)
                    @php
                        $label = $m['threshold'] >= 1_000_000
                            ? '$' . number_format($m['threshold'] / 1_000_000, 0) . 'M'
                            : '$500k';
                    @endphp
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</p>
                        @if ($m['hit'])
                            <p class="text-lg font-bold text-gray-900 dark:text-gray-100 mt-1">Year {{ $m['hit']['year'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $m['hit']['calendar'] }}</p>
                        @else
                            <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">Not in {{ $years }}yr</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $years }}-Year Projection</h3>
                    <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                        <span class="flex items-center gap-1.5">
                            <span class="inline-block w-6 h-0.5 bg-indigo-500 rounded"></span>Nominal
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="inline-block w-6 border-t-2 border-dashed border-gray-400 rounded"></span>Real ({{ $inflationRate }}% inflation)
                        </span>
                    </div>
                </div>
                <canvas id="forecastChart" class="w-full" style="max-height:380px"></canvas>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Year-by-Year (every 5 years)</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs text-gray-400 dark:text-gray-500 border-b border-gray-100 dark:border-gray-700">
                                <th class="text-left px-4 py-2 font-medium">Year</th>
                                <th class="text-right px-4 py-2 font-medium">Calendar</th>
                                <th class="text-right px-4 py-2 font-medium">Nominal</th>
                                <th class="text-right px-4 py-2 font-medium">Real (today's $)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                            @foreach ($projection as $row)
                                @if ($row['year'] % 5 === 0)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 {{ $row['year'] === 0 ? 'font-medium' : '' }}">
                                        <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">
                                            {{ $row['year'] === 0 ? 'Now' : 'Year ' . $row['year'] }}
                                        </td>
                                        <td class="px-4 py-2.5 text-right text-gray-500 dark:text-gray-400">{{ $row['label'] }}</td>
                                        <td class="px-4 py-2.5 text-right font-mono text-gray-900 dark:text-gray-100">${{ $demo->amt($row['nominal'], 0) }}</td>
                                        <td class="px-4 py-2.5 text-right font-mono text-gray-500 dark:text-gray-400">${{ $demo->amt($row['real'], 0) }}</td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @php
                // Only nominal/real are dollar figures — scaling the whole row would also
                // scramble year/label and break the chart's x-axis and milestone math.
                $jsProjection = collect($projection)->map(fn ($row) => array_merge($row, [
                    'nominal' => $demo->scaleScalar($row['nominal']),
                    'real'    => $demo->scaleScalar($row['real']),
                ]));
            @endphp
            {{-- Chart data only — forecast-charts.js (pushed to head-vite above) owns all
                 Chart.js setup and reads this blob on DOMContentLoaded. --}}
            <script>window.__forecastChart = @json(['projection' => $jsProjection]);</script>

        @endif

        </div>
        @endif

    </div>
</x-app-layout>

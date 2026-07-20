<x-app-layout>
    @push('head-vite')
        @vite(['resources/js/chartjs.js'])
    @endpush

    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Money</p>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    @if ($tab === 'cashflow')
                        {{ $month->format('F Y') }}
                    @elseif ($tab === 'trends')
                        Spending Trends
                    @else
                        50/30/20 Rule
                    @endif
                </h2>
            </div>

            <div class="flex flex-col items-end gap-2">
                {{-- Tab switcher --}}
                <div class="flex items-center gap-1">
                    <a href="{{ route('analysis', ['tab' => 'cashflow']) }}"
                       class="px-3 py-1.5 text-xs rounded-md font-medium transition
                              {{ $tab === 'cashflow'
                                  ? 'bg-gray-800 dark:bg-gray-200 text-white dark:text-gray-900'
                                  : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 border border-gray-200 dark:border-gray-600' }}">
                        Cashflow
                    </a>
                    <a href="{{ route('analysis', ['tab' => 'trends']) }}"
                       class="px-3 py-1.5 text-xs rounded-md font-medium transition
                              {{ $tab === 'trends'
                                  ? 'bg-gray-800 dark:bg-gray-200 text-white dark:text-gray-900'
                                  : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 border border-gray-200 dark:border-gray-600' }}">
                        Trends
                    </a>
                    <a href="{{ route('analysis', ['tab' => 'budget-rule']) }}"
                       class="px-3 py-1.5 text-xs rounded-md font-medium transition
                              {{ $tab === 'budget-rule'
                                  ? 'bg-gray-800 dark:bg-gray-200 text-white dark:text-gray-900'
                                  : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 border border-gray-200 dark:border-gray-600' }}">
                        50/30/20
                    </a>
                </div>

                {{-- Tab-specific controls --}}
                @if ($tab === 'cashflow')
                    <div class="flex items-center gap-1">
                        <a href="{{ route('analysis', ['tab' => 'cashflow', 'month' => $prevMonthParam]) }}"
                           class="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400 transition"
                           title="Previous month">&larr;</a>
                        @if (!$isCurrentMonth)
                            <a href="{{ route('analysis', ['tab' => 'cashflow', 'month' => $nextMonthParam]) }}"
                               class="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400 transition"
                               title="Next month">&rarr;</a>
                            <a href="{{ route('analysis', ['tab' => 'cashflow']) }}"
                               class="ml-1 text-xs text-indigo-600 dark:text-indigo-400 hover:underline">This month</a>
                        @endif
                    </div>
                @elseif ($tab === 'trends')
                    <div class="flex items-center gap-1">
                        @foreach ([3, 6, 12] as $n)
                            <a href="{{ route('analysis', ['tab' => 'trends', 'months' => $n]) }}"
                               class="px-2.5 py-1 text-xs rounded-md font-medium transition
                                      {{ $lookback === $n
                                          ? 'bg-indigo-600 text-white'
                                          : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 border border-gray-200 dark:border-gray-600' }}">
                                {{ $n }}M
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-12">

        {{-- ═══════════════════════════════ CASHFLOW TAB ═══════════════════════════════ --}}
        @if ($tab === 'cashflow')
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-stat-tile>
                    <x-slot:label>Income</x-slot:label>
                    <p class="mt-1 text-2xl font-semibold font-mono text-green-600 dark:text-green-400">
                        ${{ $demo->amt($income) }}
                    </p>
                </x-stat-tile>
                <x-stat-tile>
                    <x-slot:label>Spent</x-slot:label>
                    <p class="mt-1 text-2xl font-semibold font-mono text-red-600 dark:text-red-400">
                        ${{ $demo->amt($totalSpent) }}
                    </p>
                </x-stat-tile>
                <x-stat-tile>
                    <x-slot:label>Net</x-slot:label>
                    <p class="mt-1 text-2xl font-semibold font-mono {{ $net >= 0 ? 'text-gray-900 dark:text-gray-100' : 'text-red-600 dark:text-red-400' }}">
                        {{ $net < 0 ? '−' : '' }}${{ $demo->amt(abs($net)) }}
                    </p>
                </x-stat-tile>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Income</h3>
                    <a href="{{ route('cash-accounts.index') }}"
                       class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">+ Add deposit</a>
                </div>
                @if ($incomeRows->isEmpty())
                    <div class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">No deposits recorded for this month.</div>
                @else
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($incomeRows as $entry)
                            <div class="px-6 py-3 flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-800 dark:text-gray-200">{{ $entry->description ?: '—' }}</p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500">{{ $entry->occurred_at->format('M j') }}</p>
                                </div>
                                <span class="text-sm font-mono font-semibold text-green-600 dark:text-green-400">
                                    ${{ $demo->amt($entry->amount) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">6-Month History</h3>
                <canvas id="cashflowChart" height="110"></canvas>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Spending by Envelope</h3>
                </div>
                @if ($envelopeRows->isEmpty())
                    <div class="p-6 text-sm text-gray-500 dark:text-gray-400">No envelope activity for this month.</div>
                @else
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($envelopeRows as $row)
                            @php
                                $pct  = $row['target'] > 0 ? min(100, round($row['spent'] / $row['target'] * 100)) : null;
                                $over = $row['target'] > 0 && $row['spent'] > $row['target'];
                            @endphp
                            <div class="px-6 py-4">
                                <div class="flex items-center justify-between mb-1.5">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-block w-2.5 h-2.5 rounded-full shrink-0"
                                              style="background-color: {{ $row['envelope']->color }}"></span>
                                        <a href="{{ route('envelopes.show', $row['envelope']) }}"
                                           class="text-sm font-medium text-gray-800 dark:text-gray-200 hover:underline">
                                            {{ $row['envelope']->name }}
                                        </a>
                                    </div>
                                    <div class="text-sm font-mono text-right shrink-0 ml-4">
                                        <span class="{{ $over ? 'text-red-600 dark:text-red-400' : 'text-gray-800 dark:text-gray-200' }} font-semibold">
                                            ${{ $demo->amt($row['spent']) }}
                                        </span>
                                        @if ($row['target'] > 0)
                                            <span class="text-gray-400 dark:text-gray-500"> / ${{ $demo->amt($row['target']) }}</span>
                                        @endif
                                    </div>
                                </div>
                                @if ($pct !== null)
                                    <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-1.5 overflow-hidden">
                                        <div class="h-full rounded-full transition-all"
                                             style="width: {{ $pct }}%; background-color: {{ $over ? '#dc2626' : $row['envelope']->color }};"></div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>

        {{-- ═══════════════════════════════ TRENDS TAB ═══════════════════════════════ --}}
        @elseif ($tab === 'trends')
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @if ($datasets->isEmpty())
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-8 text-center text-gray-500 dark:text-gray-400 text-sm">
                    No envelope spending recorded in the last {{ $lookback }} months.
                </div>
            @else
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5">
                    <canvas id="trendsChart" height="120"></canvas>
                </div>

                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-700">
                                <th class="px-5 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Envelope</th>
                                @foreach ($monthLabels as $label)
                                    <th class="px-3 py-3 text-right font-semibold text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $label }}</th>
                                @endforeach
                                <th class="px-5 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/60">
                            @foreach ($datasets as $ds)
                                @php $rowTotal = array_sum($ds['data']->toArray()); @endphp
                                <tr class="hover:bg-gray-50/60 dark:hover:bg-gray-700/30 transition">
                                    <td class="px-5 py-3 text-gray-800 dark:text-gray-200">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-block w-2.5 h-2.5 rounded-full shrink-0"
                                                  style="background-color: {{ $ds['color'] }}"></span>
                                            {{ $ds['label'] }}
                                        </div>
                                    </td>
                                    @foreach ($ds['data'] as $val)
                                        <td class="px-3 py-3 text-right font-mono text-gray-700 dark:text-gray-300">
                                            {{ $val > 0 ? '$'.$demo->amt($val) : '—' }}
                                        </td>
                                    @endforeach
                                    <td class="px-5 py-3 text-right font-mono font-semibold text-gray-800 dark:text-gray-200">
                                        ${{ $demo->amt($rowTotal) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-200 dark:border-gray-600">
                                <td class="px-5 py-3 font-semibold text-gray-700 dark:text-gray-300">Total</td>
                                @foreach ($monthlyTotals as $total)
                                    <td class="px-3 py-3 text-right font-mono font-semibold text-gray-800 dark:text-gray-200">
                                        ${{ $demo->amt($total) }}
                                    </td>
                                @endforeach
                                <td class="px-5 py-3 text-right font-mono font-semibold text-gray-800 dark:text-gray-200">
                                    ${{ $demo->amt($monthlyTotals->sum()) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif

        </div>

        {{-- ═══════════════════════════════ 50/30/20 TAB ═══════════════════════════════ --}}
        @else
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @php
                $hasData = $data['has_data'];
                $ratios  = $data['ratios'];
                $targets = $data['targets'];
                $drift   = $data['drift'];
            @endphp

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-sm text-gray-600 dark:text-gray-400 space-y-2">
                <p class="text-gray-700 dark:text-gray-300">
                    The 50/30/20 rule splits monthly income into three buckets:
                    <strong>50% needs</strong> (rent, utilities, groceries, insurance — fixed costs you can't easily cut),
                    <strong>30% wants</strong> (dining, entertainment, subscriptions, and sinking funds for planned purchases like a vacation or gadget),
                    and <strong>20% wealth building</strong> (emergency fund until it's fully funded, then retirement contributions and investments).
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Figures below average the trailing {{ $data['window_months'] }} months
                    ({{ $data['window_start']->format('M Y') }} – {{ $data['window_end']->format('M Y') }}).
                    Mark envelopes as <em>mandatory</em> or <em>wealth building</em> in their edit form to classify them.
                </p>
            </div>

            @php
                // Seed from real income normally; in demo mode use a round placeholder, since the
                // seed lands in the DOM unmasked (everything typed after is the visitor's own number).
                $seedIncome = $demo->isActive() ? 5000.0 : (float) ($data['monthly_income'] ?? 0);
                $seedSplit  = fn ($pct) => '$'.number_format(round($seedIncome * $pct / 100));
            @endphp
            <div
                x-data="budgetCalculator(
                    {{ $seedIncome }},
                    {{ (float) $targets['mandatory'] }},
                    {{ (float) $targets['discretionary'] }},
                    {{ (float) $targets['savings'] }}
                )"
                class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5 space-y-4"
            >
                <div>
                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">Try any amount</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                        Not your real numbers — just applies the 50/30/20 split to whatever you type in.
                    </p>
                </div>

                <div class="max-w-xs">
                    <label for="manual-income" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Monthly income</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-gray-500 dark:text-gray-400 pointer-events-none">$</span>
                        <input id="manual-income" type="number" min="0" step="0.01" x-model.number="income"
                               class="block w-full pl-7 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    {{-- Tiles are pre-rendered with the seeded split so they read correctly before
                         Alpine hydrates; x-text overwrites them on init and on every keystroke. --}}
                    <x-stat-tile>
                        <x-slot:label>Needs</x-slot:label>
                        <p class="mt-1 text-2xl font-semibold font-mono text-indigo-600 dark:text-indigo-400" x-text="fmt(needs)">{{ $seedSplit($targets['mandatory']) }}</p>
                        <x-slot:caption><span x-text="mandatoryPct">{{ $targets['mandatory'] }}</span>%</x-slot:caption>
                    </x-stat-tile>
                    <x-stat-tile>
                        <x-slot:label>Wants</x-slot:label>
                        <p class="mt-1 text-2xl font-semibold font-mono text-sky-600 dark:text-sky-400" x-text="fmt(wants)">{{ $seedSplit($targets['discretionary']) }}</p>
                        <x-slot:caption><span x-text="discretionaryPct">{{ $targets['discretionary'] }}</span>%</x-slot:caption>
                    </x-stat-tile>
                    <x-stat-tile>
                        <x-slot:label>Wealth Building</x-slot:label>
                        <p class="mt-1 text-2xl font-semibold font-mono text-emerald-600 dark:text-emerald-400" x-text="fmt(savings)">{{ $seedSplit($targets['savings']) }}</p>
                        <x-slot:caption><span x-text="savingsPct">{{ $targets['savings'] }}</span>%</x-slot:caption>
                    </x-stat-tile>
                </div>
            </div>

            @if (! $hasData)
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-sm text-gray-600 dark:text-gray-400 space-y-2">
                    <p class="font-semibold text-gray-800 dark:text-gray-200">No income recorded in the last 6 months.</p>
                    <p>Add income entries so the calculator can compute your allocation ratios.</p>
                    <a href="{{ route('ready-to-assign') }}" class="inline-block mt-2 text-indigo-600 dark:text-indigo-400 hover:underline">Go to Ready to Assign &rarr;</a>
                </div>
            @else
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <x-stat-tile>
                        <x-slot:label>Monthly income</x-slot:label>
                        <p class="mt-1 text-2xl font-semibold font-mono text-gray-800 dark:text-gray-100">
                            ${{ $demo->amt($data['monthly_income']) }}
                        </p>
                        <x-slot:caption>avg / month</x-slot:caption>
                    </x-stat-tile>
                    <x-stat-tile title="Envelopes marked 'Mandatory' — rent, utilities, groceries, insurance. Target: 50% or less.">
                        <x-slot:label>Needs</x-slot:label>
                        <p class="mt-1 text-2xl font-semibold font-mono {{ $drift['mandatory_over'] ? 'text-amber-600 dark:text-amber-400' : 'text-gray-800 dark:text-gray-100' }}">
                            ${{ $demo->amt($data['monthly_mandatory']) }}
                        </p>
                        <x-slot:caption>
                            <span class="{{ $drift['mandatory_over'] ? 'text-amber-600 dark:text-amber-400' : '' }}">{{ $ratios['mandatory'] }}% (target ≤ {{ $targets['mandatory'] }}%)</span>
                        </x-slot:caption>
                    </x-stat-tile>
                    <x-stat-tile title="Everything not tagged mandatory or wealth building — dining, entertainment, subscriptions, and sinking funds for planned purchases. Target: 30% or less.">
                        <x-slot:label>Wants</x-slot:label>
                        <p class="mt-1 text-2xl font-semibold font-mono text-gray-800 dark:text-gray-100">
                            ${{ $demo->amt($data['monthly_discretionary']) }}
                        </p>
                        <x-slot:caption>{{ $ratios['discretionary'] }}% (target ≤ {{ $targets['discretionary'] }}%)</x-slot:caption>
                    </x-stat-tile>
                    <x-stat-tile title="Envelopes marked 'Wealth building' — emergency fund and investing. Target: 20% or more.">
                        <x-slot:label>Wealth Building</x-slot:label>
                        <p class="mt-1 text-2xl font-semibold font-mono {{ $drift['savings_under'] ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400' }}">
                            ${{ $demo->amt($data['monthly_savings']) }}
                        </p>
                        <x-slot:caption>
                            <span class="{{ $drift['savings_under'] ? 'text-amber-600 dark:text-amber-400' : '' }}">{{ $ratios['savings'] }}% (target ≥ {{ $targets['savings'] }}%)</span>
                        </x-slot:caption>
                    </x-stat-tile>
                </div>

                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5 space-y-3">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Your allocation</p>
                    <div class="flex w-full h-6 rounded-full overflow-hidden bg-gray-100 dark:bg-gray-700">
                        @php
                            $m = max(0, min(100, $ratios['mandatory'] ?? 0));
                            $d = max(0, min(100 - $m, $ratios['discretionary'] ?? 0));
                            $s = max(0, 100 - $m - $d);
                        @endphp
                        <div class="h-full bg-indigo-500" style="width: {{ $m }}%" title="Needs {{ $ratios['mandatory'] }}% — fixed costs like rent, utilities, groceries"></div>
                        <div class="h-full bg-sky-400"    style="width: {{ $d }}%" title="Wants {{ $ratios['discretionary'] }}% — spending and planned purchases"></div>
                        <div class="h-full bg-emerald-500" style="width: {{ $s }}%" title="Wealth Building {{ $ratios['savings'] }}% — emergency fund and investing"></div>
                    </div>
                    <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                        <span title="Fixed costs you can't easily cut"><span class="inline-block w-2 h-2 rounded-full bg-indigo-500 mr-1"></span>Needs</span>
                        <span title="Day-to-day spending and sinking funds for planned purchases"><span class="inline-block w-2 h-2 rounded-full bg-sky-400 mr-1"></span>Wants</span>
                        <span title="Emergency fund and investing — grows your net worth"><span class="inline-block w-2 h-2 rounded-full bg-emerald-500 mr-1"></span>Wealth Building</span>
                    </div>
                </div>

                <x-budget-rule-drift-banner :drift="$drift" :ratios="$ratios" detailed />

                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Where the 20% should go</h3>
                        <span class="text-xs uppercase tracking-wide font-semibold {{ $data['phase'] === 'funded' ? 'text-emerald-600 dark:text-emerald-400' : 'text-indigo-600 dark:text-indigo-400' }}">
                            {{ $data['phase'] === 'funded' ? 'Emergency fund complete' : 'Building emergency fund' }}
                        </span>
                    </div>

                    @if ($data['emergency_envelope'])
                        @php
                            $pct       = $data['emergency_target'] > 0
                                ? min(100, round($data['emergency_balance'] / $data['emergency_target'] * 100))
                                : 0;
                            $remaining = max(0, $data['emergency_target'] - $data['emergency_balance']);
                        @endphp
                        <div>
                            <div class="flex justify-between text-sm mb-1.5">
                                <a href="{{ route('envelopes.show', $data['emergency_envelope']) }}"
                                   class="font-medium text-gray-700 dark:text-gray-300 hover:underline">
                                    {{ $data['emergency_envelope']->name }} ({{ $data['target_months'] }}-month target)
                                </a>
                                <span class="font-mono text-gray-600 dark:text-gray-400">
                                    ${{ $demo->amt($data['emergency_balance']) }} / ${{ $demo->amt($data['emergency_target']) }}
                                    <span class="text-gray-400 dark:text-gray-500 ml-1">({{ $pct }}%)</span>
                                </span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2.5 overflow-hidden">
                                <div class="h-full rounded-full transition-all {{ $data['emergency_funded'] ? 'bg-emerald-500' : 'bg-indigo-500' }}"
                                     style="width: {{ $pct }}%"></div>
                            </div>
                            @if (! $data['emergency_funded'] && $remaining > 0)
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                    ${{ $demo->amt($remaining) }} to go — direct your 20% here first.
                                </p>
                            @endif
                        </div>
                    @else
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            No envelope is marked as your emergency fund.
                            <a href="{{ route('envelopes.index') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Tag one &rarr;</a>
                        </div>
                    @endif

                    @if ($data['phase'] === 'funded')
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            Emergency fund is funded.
                            @if ($data['other_savings']->isNotEmpty())
                                Redirect your 20% to:
                                <ul class="list-disc list-inside mt-1.5 space-y-0.5">
                                    @foreach ($data['other_savings'] as $env)
                                        <li>
                                            <a href="{{ route('envelopes.show', $env) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $env->name }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                Tag retirement or investing envelopes as <em>wealth building</em> to direct future contributions there.
                            @endif
                        </div>
                    @endif
                </div>
            @endif

        </div>
        @endif

    </div>

    @push('scripts')
    @if ($tab === 'cashflow')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const history = @json($history);
        const { gridColor: grid, labelColor: ticks } = window.themeColors();

        new Chart(document.getElementById('cashflowChart'), {
            type: 'bar',
            data: {
                labels: history.map(h => h.month),
                datasets: [
                    { label: 'Income', data: history.map(h => h.income), backgroundColor: 'rgba(34,197,94,0.65)', borderColor: 'rgba(34,197,94,1)', borderWidth: 1, borderRadius: 3 },
                    { label: 'Spent',  data: history.map(h => h.spent),  backgroundColor: 'rgba(239,68,68,0.65)', borderColor: 'rgba(239,68,68,1)',  borderWidth: 1, borderRadius: 3 },
                ],
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { labels: { color: ticks } },
                    tooltip: { callbacks: { label: ctx => ' $' + ctx.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2 }) } },
                },
                scales: {
                    x: { ticks: { color: ticks }, grid: { color: grid } },
                    y: { beginAtZero: true, ticks: { color: ticks, callback: v => '$' + v.toLocaleString('en-US') }, grid: { color: grid } },
                },
            },
        });
    });
    </script>
    @elseif ($tab === 'trends' && $datasets->isNotEmpty())
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const labels   = @json($monthLabels);
        const datasets = @json($datasets);
        const { gridColor: grid, labelColor: ticks } = window.themeColors();

        new Chart(document.getElementById('trendsChart'), {
            type: 'bar',
            data: {
                labels,
                datasets: datasets.map(ds => ({
                    label: ds.label, data: ds.data,
                    backgroundColor: ds.color + 'cc', borderColor: ds.color,
                    borderWidth: 1, borderRadius: 2, stack: 'spend',
                })),
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom', labels: { color: ticks, boxWidth: 12, padding: 16 } },
                    tooltip: { callbacks: { label: ctx => ' ' + ctx.dataset.label + ': $' + ctx.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2 }) } },
                },
                scales: {
                    x: { stacked: true, ticks: { color: ticks }, grid: { color: grid } },
                    y: { stacked: true, beginAtZero: true, ticks: { color: ticks, callback: v => '$' + v.toLocaleString('en-US') }, grid: { color: grid } },
                },
            },
        });
    });
    </script>
    @endif
    @endpush
</x-app-layout>

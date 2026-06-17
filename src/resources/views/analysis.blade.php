<x-app-layout>
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
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Income</p>
                    <p class="mt-1 text-2xl font-semibold font-mono text-green-600 dark:text-green-400">
                        ${{ number_format($income, 2) }}
                    </p>
                </div>
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Spent</p>
                    <p class="mt-1 text-2xl font-semibold font-mono text-red-600 dark:text-red-400">
                        ${{ number_format($totalSpent, 2) }}
                    </p>
                </div>
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Net</p>
                    <p class="mt-1 text-2xl font-semibold font-mono {{ $net >= 0 ? 'text-gray-900 dark:text-gray-100' : 'text-red-600 dark:text-red-400' }}">
                        {{ $net < 0 ? '−' : '' }}${{ number_format(abs($net), 2) }}
                    </p>
                </div>
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
                                    ${{ number_format($entry->amount, 2) }}
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
                                            ${{ number_format($row['spent'], 2) }}
                                        </span>
                                        @if ($row['target'] > 0)
                                            <span class="text-gray-400 dark:text-gray-500"> / ${{ number_format($row['target'], 2) }}</span>
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
                                            {{ $val > 0 ? '$'.number_format($val, 2) : '—' }}
                                        </td>
                                    @endforeach
                                    <td class="px-5 py-3 text-right font-mono font-semibold text-gray-800 dark:text-gray-200">
                                        ${{ number_format($rowTotal, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-200 dark:border-gray-600">
                                <td class="px-5 py-3 font-semibold text-gray-700 dark:text-gray-300">Total</td>
                                @foreach ($monthlyTotals as $total)
                                    <td class="px-3 py-3 text-right font-mono font-semibold text-gray-800 dark:text-gray-200">
                                        ${{ number_format($total, 2) }}
                                    </td>
                                @endforeach
                                <td class="px-5 py-3 text-right font-mono font-semibold text-gray-800 dark:text-gray-200">
                                    ${{ number_format($monthlyTotals->sum(), 2) }}
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

            @if (! $hasData)
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-sm text-gray-600 dark:text-gray-400 space-y-2">
                    <p class="font-semibold text-gray-800 dark:text-gray-200">No income recorded in the last 6 months.</p>
                    <p>Add income entries so the calculator can compute your allocation ratios.</p>
                    <a href="{{ route('ready-to-assign') }}" class="inline-block mt-2 text-indigo-600 dark:text-indigo-400 hover:underline">Go to Ready to Assign &rarr;</a>
                </div>
            @else
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Monthly income</p>
                        <p class="mt-1 text-2xl font-semibold font-mono text-gray-800 dark:text-gray-100">
                            ${{ number_format($data['monthly_income'], 2) }}
                        </p>
                        <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">avg / month</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4"
                         title="Envelopes marked 'Mandatory' — rent, utilities, groceries, insurance. Target: 50% or less.">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Needs</p>
                        <p class="mt-1 text-2xl font-semibold font-mono {{ $drift['mandatory_over'] ? 'text-amber-600 dark:text-amber-400' : 'text-gray-800 dark:text-gray-100' }}">
                            ${{ number_format($data['monthly_mandatory'], 2) }}
                        </p>
                        <p class="mt-0.5 text-xs {{ $drift['mandatory_over'] ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400 dark:text-gray-500' }}">
                            {{ $ratios['mandatory'] }}% (target ≤ {{ $targets['mandatory'] }}%)
                        </p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4"
                         title="Everything not tagged mandatory or wealth building — dining, entertainment, subscriptions, and sinking funds for planned purchases. Target: 30% or less.">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Wants</p>
                        <p class="mt-1 text-2xl font-semibold font-mono text-gray-800 dark:text-gray-100">
                            ${{ number_format($data['monthly_discretionary'], 2) }}
                        </p>
                        <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                            {{ $ratios['discretionary'] }}% (target ≤ {{ $targets['discretionary'] }}%)
                        </p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4"
                         title="Envelopes marked 'Wealth building' — emergency fund and investing. Target: 20% or more.">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Wealth Building</p>
                        <p class="mt-1 text-2xl font-semibold font-mono {{ $drift['savings_under'] ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400' }}">
                            ${{ number_format($data['monthly_savings'], 2) }}
                        </p>
                        <p class="mt-0.5 text-xs {{ $drift['savings_under'] ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400 dark:text-gray-500' }}">
                            {{ $ratios['savings'] }}% (target ≥ {{ $targets['savings'] }}%)
                        </p>
                    </div>
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
                                    ${{ number_format($data['emergency_balance'], 2) }} / ${{ number_format($data['emergency_target'], 2) }}
                                    <span class="text-gray-400 dark:text-gray-500 ml-1">({{ $pct }}%)</span>
                                </span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2.5 overflow-hidden">
                                <div class="h-full rounded-full transition-all {{ $data['emergency_funded'] ? 'bg-emerald-500' : 'bg-indigo-500' }}"
                                     style="width: {{ $pct }}%"></div>
                            </div>
                            @if (! $data['emergency_funded'] && $remaining > 0)
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                    ${{ number_format($remaining, 2) }} to go — direct your 20% here first.
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
    (function () {
        const history = @json($history);
        const isDark  = document.documentElement.classList.contains('dark');
        const grid    = isDark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.06)';
        const ticks   = isDark ? '#9ca3af' : '#6b7280';

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
    })();
    </script>
    @elseif ($tab === 'trends' && $datasets->isNotEmpty())
    <script>
    (function () {
        const labels   = @json($monthLabels);
        const datasets = @json($datasets);
        const isDark   = document.documentElement.classList.contains('dark');
        const grid     = isDark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.06)';
        const ticks    = isDark ? '#9ca3af' : '#6b7280';

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
    })();
    </script>
    @endif
    @endpush
</x-app-layout>

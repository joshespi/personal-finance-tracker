<x-app-layout>
    @push('head-vite')
        @vite(['resources/js/chartjs.js'])
    @endpush

    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Net Worth Forecast</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Project your future wealth based on current net worth and savings rate.</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Input form --}}
            <form method="GET" action="{{ route('forecast') }}" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 space-y-5">
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
                            <input type="number" name="starting_nw" value="{{ $startingNw }}" step="any"
                                   class="pl-7 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" />
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
                            <input type="number" name="monthly_savings" value="{{ $monthlySavings }}" step="any" min="0"
                                   class="pl-7 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">FIRE Target <span class="text-gray-400 font-normal">(optional)</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 dark:text-gray-400 text-sm">$</span>
                            <input type="number" name="fire_target" value="{{ $fireTarget ?? '' }}" step="any" min="0" placeholder="1000000"
                                   class="pl-7 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" />
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

            {{-- FIRE target highlight --}}
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

            {{-- Standard milestones --}}
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

            {{-- Projection chart --}}
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

            {{-- Year-by-year table (every 5 years) --}}
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
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const projection = @json($jsProjection);
        const { isDark, gridColor, labelColor } = window.themeColors();

        function fmtAxis(v) {
            if (v >= 1_000_000) return '$' + (v / 1_000_000).toFixed(1) + 'M';
            if (v >= 1_000)     return '$' + (v / 1_000).toFixed(0) + 'k';
            return '$' + v;
        }

        function fmtFull(v) {
            return '$' + v.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        }

        new Chart(document.getElementById('forecastChart'), {
            type: 'line',
            data: {
                labels: projection.map(p => p.label),
                datasets: [
                    {
                        label: 'Nominal',
                        data: projection.map(p => p.nominal),
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99,102,241,0.08)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                    },
                    {
                        label: 'Real (inflation-adjusted)',
                        data: projection.map(p => p.real),
                        borderColor: isDark ? '#6b7280' : '#9ca3af',
                        borderDash: [6, 4],
                        fill: false,
                        tension: 0.35,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ctx.dataset.label + ': ' + fmtFull(ctx.raw),
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { color: gridColor },
                        ticks: { color: labelColor, maxTicksLimit: 10 },
                    },
                    y: {
                        grid: { color: gridColor },
                        ticks: { color: labelColor, callback: fmtAxis },
                    },
                },
            },
        });
    });
    </script>
</x-app-layout>

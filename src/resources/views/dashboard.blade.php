<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Dashboard</h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('transfers.create') }}"
                   class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                    Portfolio Transfer
                </a>
                <a href="{{ route('portfolios.create') }}"
                   class="inline-flex items-center px-3 py-1.5 bg-gray-800 dark:bg-gray-700 border border-transparent rounded-md text-xs font-semibold text-white hover:bg-gray-700 dark:hover:bg-gray-600 transition">
                    + New Portfolio
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @if ($summaries->isEmpty())
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-8 text-center">
                    <p class="text-gray-500 dark:text-gray-400 mb-4">No portfolios yet.</p>
                    <a href="{{ route('portfolios.create') }}"
                       class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-700 rounded-md text-sm font-semibold text-white hover:bg-gray-700 dark:hover:bg-gray-600 transition">
                        Create your first portfolio
                    </a>
                </div>
            @else

                {{-- Top-level totals --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Cost Basis</p>
                        <p class="mt-1 text-2xl font-semibold font-mono text-gray-900 dark:text-gray-100">
                            ${{ number_format($totals['cost_basis'], 2) }}
                        </p>
                    </div>

                    @if ($totals['market_value'] !== null)
                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Market Value</p>
                            <p class="mt-1 text-2xl font-semibold font-mono text-gray-900 dark:text-gray-100">
                                ${{ number_format($totals['market_value'], 2) }}
                            </p>
                        </div>

                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Unrealized P&L</p>
                            @php $unr = $totals['unrealized'] ?? 0; @endphp
                            <p class="mt-1 text-2xl font-semibold font-mono {{ $unr >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $unr >= 0 ? '+' : '' }}${{ number_format($unr, 2) }}
                            </p>
                        </div>
                    @endif

                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Assets</p>
                        <p class="mt-1 text-2xl font-semibold font-mono text-gray-900 dark:text-gray-100">
                            ${{ number_format($totals['total_value'], 2) }}
                        </p>
                    </div>
                </div>

                @if ($chartData->isNotEmpty())
                    {{-- Portfolio value chart with time range toggles --}}
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Portfolio Value</h3>
                            <div class="flex flex-wrap gap-1" id="dash-range-btns">
                                @foreach (['5D','1W','1M','3M','6M','1Y','YTD','5Y','10Y','All'] as $r)
                                    <button data-range="{{ $r }}"
                                            class="range-btn px-2.5 py-1 text-xs rounded font-medium transition
                                                   bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300
                                                   hover:bg-gray-200 dark:hover:bg-gray-600">
                                        {{ $r }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        <div id="dashChart" class="h-64"></div>
                    </div>

                    {{-- Benchmark comparison toggle (shows when benchmark data exists) --}}
                    @if (!empty($benchmarkData))
                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Benchmark Comparison</h3>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Normalized to 100 at start of period — shows relative % return</p>
                                </div>
                                <div class="flex flex-wrap gap-1" id="bench-range-btns">
                                    @foreach (['1M','3M','6M','1Y','5Y','10Y','All'] as $r)
                                        <button data-range="{{ $r }}"
                                                class="bench-range-btn px-2.5 py-1 text-xs rounded font-medium transition
                                                       bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300
                                                       hover:bg-gray-200 dark:hover:bg-gray-600">
                                            {{ $r }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                            <div id="benchmarkChart" class="h-64"></div>
                        </div>
                    @endif
                @endif

                {{-- Asset allocation donut --}}
                @if ($allocation['total'] > 0)
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">Asset Allocation</h3>
                        <div class="flex flex-col sm:flex-row items-center gap-8">
                            <div id="allocationDonut" class="w-64 h-64 shrink-0"></div>
                            <div class="space-y-2 text-sm">
                                @php
                                    $allocColors = ['#6366f1','#f97316','#10b981'];
                                @endphp
                                @foreach ($allocation['labels'] as $i => $label)
                                    @php $val = $allocation['values'][$i]; @endphp
                                    @if ($val > 0)
                                        <div class="flex items-center gap-3">
                                            <span class="w-3 h-3 rounded-full shrink-0" style="background:{{ $allocColors[$i] }}"></span>
                                            <span class="text-gray-700 dark:text-gray-300 w-28">{{ $label }}</span>
                                            <span class="font-mono text-gray-900 dark:text-gray-100">${{ number_format($val, 2) }}</span>
                                            <span class="text-gray-400 dark:text-gray-500">
                                                ({{ $allocation['total'] > 0 ? number_format($val / $allocation['total'] * 100, 1) : 0 }}%)
                                            </span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                {{-- All Holdings --}}
                @if ($allHoldings->isNotEmpty())
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">All Holdings</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Symbol</th>
                                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                                        <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total Qty</th>
                                        <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cost Basis</th>
                                        <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Price</th>
                                        <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Market Value</th>
                                        <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Unrealized P&L</th>
                                        <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">% of Total</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach ($allHoldings as $h)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                            <td class="px-5 py-3 font-mono font-semibold text-gray-900 dark:text-gray-100">
                                                {{ $h['asset']->symbol }}
                                            </td>
                                            <td class="px-5 py-3">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                    {{ $h['asset']->asset_type === 'crypto'
                                                        ? 'bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300'
                                                        : 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300' }}">
                                                    {{ ucfirst($h['asset']->asset_type) }}
                                                </span>
                                            </td>
                                            <td class="px-5 py-3 text-right font-mono text-gray-900 dark:text-gray-100">
                                                {{ rtrim(rtrim(number_format((float)$h['quantity'], 8), '0'), '.') }}
                                            </td>
                                            <td class="px-5 py-3 text-right font-mono text-gray-700 dark:text-gray-300">
                                                ${{ number_format($h['total_cost'], 2) }}
                                            </td>
                                            <td class="px-5 py-3 text-right font-mono text-gray-500 dark:text-gray-400">
                                                {{ $h['current_price'] !== null ? '$' . number_format($h['current_price'], 4) : '—' }}
                                            </td>
                                            <td class="px-5 py-3 text-right font-mono font-semibold text-gray-900 dark:text-gray-100">
                                                {{ $h['current_value'] !== null ? '$' . number_format($h['current_value'], 2) : '—' }}
                                            </td>
                                            <td class="px-5 py-3 text-right font-mono font-semibold">
                                                @if ($h['unrealized_gain'] !== null)
                                                    @php $g = $h['unrealized_gain']; @endphp
                                                    <span class="{{ $g >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                        {{ $g >= 0 ? '+' : '' }}${{ number_format($g, 2) }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>
                                            <td class="px-5 py-3 text-right font-mono text-gray-500 dark:text-gray-400">
                                                {{ number_format($h['pct'], 1) }}%
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Per-portfolio breakdown --}}
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Portfolios</h3>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($summaries as $s)
                            <div class="px-6 py-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <div>
                                    <a href="{{ route('portfolios.show', $s['portfolio']) }}"
                                       class="font-medium text-gray-900 dark:text-gray-100 hover:underline">
                                        {{ $s['portfolio']->name }}
                                    </a>
                                    @if ($s['portfolio']->description)
                                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $s['portfolio']->description }}</p>
                                    @endif
                                </div>
                                <div class="flex items-center gap-8 text-right text-sm shrink-0 ms-4">
                                    <div>
                                        <p class="text-xs text-gray-400 dark:text-gray-500">Cost Basis</p>
                                        <p class="font-mono text-gray-700 dark:text-gray-300">${{ number_format($s['cost_basis'], 2) }}</p>
                                    </div>
                                    @if ($s['market_value'] !== null)
                                        <div>
                                            <p class="text-xs text-gray-400 dark:text-gray-500">Market Value</p>
                                            <p class="font-mono text-gray-900 dark:text-gray-100 font-semibold">${{ number_format($s['market_value'], 2) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-400 dark:text-gray-500">P&L</p>
                                            @php $unr = $s['unrealized'] ?? 0; @endphp
                                            <p class="font-mono font-semibold {{ $unr >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                {{ $unr >= 0 ? '+' : '' }}${{ number_format($unr, 2) }}
                                            </p>
                                        </div>
                                    @endif
                                    @if ($s['manual_value'] > 0)
                                        <div>
                                            <p class="text-xs text-gray-400 dark:text-gray-500">Manual</p>
                                            <p class="font-mono text-gray-700 dark:text-gray-300">${{ number_format($s['manual_value'], 2) }}</p>
                                        </div>
                                    @endif
                                    <a href="{{ route('portfolios.show', $s['portfolio']) }}"
                                       class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                                        View
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

            @endif
        </div>
    </div>

    @if ($chartData->isNotEmpty())
        @push('scripts')
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const isDark      = document.documentElement.classList.contains('dark');
            const gridColor   = isDark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.07)';
            const labelColor  = isDark ? '#9ca3af' : '#6b7280';
            const allData     = @json($chartData);      // [{date, value, cost}]
            const benchRaw    = @json($benchmarkData);  // {SPY: [{date, price}], BTC: [{date, price}]}
            const allocData   = @json($allocation);

            // ── Helpers ──────────────────────────────────────────────
            function cutoffDate(range) {
                const now = new Date();
                const y = now.getFullYear(), m = now.getMonth(), d = now.getDate();
                switch (range) {
                    case '5D':  return new Date(y, m, d - 5);
                    case '1W':  return new Date(y, m, d - 7);
                    case '1M':  return new Date(y, m - 1, d);
                    case '3M':  return new Date(y, m - 3, d);
                    case '6M':  return new Date(y, m - 6, d);
                    case '1Y':  return new Date(y - 1, m, d);
                    case 'YTD': return new Date(y, 0, 1);
                    case '5Y':  return new Date(y - 5, m, d);
                    case '10Y': return new Date(y - 10, m, d);
                    default:    return null; // All
                }
            }

            function filterByRange(data, range) {
                const cut = cutoffDate(range);
                return cut ? data.filter(r => new Date(r.date) >= cut) : data;
            }

            function activateBtn(containerSelector, btnSelector, activeRange) {
                document.querySelectorAll(containerSelector + ' button').forEach(b => {
                    const active = b.dataset.range === activeRange;
                    b.classList.toggle('bg-indigo-600', active);
                    b.classList.toggle('text-white', active);
                    b.classList.toggle('dark:bg-indigo-500', active);
                    b.classList.toggle('bg-gray-100', !active);
                    b.classList.toggle('dark:bg-gray-700', !active);
                    b.classList.toggle('text-gray-600', !active);
                    b.classList.toggle('dark:text-gray-300', !active);
                });
            }

            function apexBase(dark) {
                return {
                    chart:  { background: 'transparent', toolbar: { show: false }, animations: { enabled: false } },
                    theme:  { mode: dark ? 'dark' : 'light' },
                    grid:   { borderColor: gridColor, strokeDashArray: 3 },
                    xaxis:  { type: 'datetime', labels: { style: { colors: labelColor }, datetimeUTC: false } },
                    yaxis:  { labels: { style: { colors: labelColor }, formatter: v => '$' + v.toLocaleString('en-US', { maximumFractionDigits: 0 }) } },
                    tooltip: { x: { format: 'MMM d, yyyy' }, theme: dark ? 'dark' : 'light' },
                    stroke: { curve: 'smooth', width: 2 },
                    legend: { position: 'bottom', labels: { colors: labelColor } },
                };
            }

            // ── Portfolio Value Chart ─────────────────────────────────
            let dashRange = '1Y';
            const dashEl  = document.getElementById('dashChart');

            const dashChart = new ApexCharts(dashEl, {
                ...apexBase(isDark),
                chart: { ...apexBase(isDark).chart, type: 'area', height: 256 },
                series: [],
                fill:  { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.02 } },
            });
            dashChart.render();

            function updateDashChart(range) {
                const filtered = filterByRange(allData, range);
                dashChart.updateSeries([
                    { name: 'Portfolio Value', data: filtered.map(r => [new Date(r.date).getTime(), r.value]) },
                    { name: 'Cost Basis',      data: filtered.map(r => [new Date(r.date).getTime(), r.cost]),
                      type: 'line', stroke: { dashArray: 5 } },
                ]);
                activateBtn('#dash-range-btns', 'button', range);
            }

            document.querySelectorAll('#dash-range-btns button').forEach(b =>
                b.addEventListener('click', () => { dashRange = b.dataset.range; updateDashChart(dashRange); })
            );
            updateDashChart(dashRange);

            // ── Benchmark Chart ───────────────────────────────────────
            const benchEl = document.getElementById('benchmarkChart');
            if (benchEl && Object.keys(benchRaw).length > 0) {
                let benchRange = '1Y';
                const benchChart = new ApexCharts(benchEl, {
                    ...apexBase(isDark),
                    chart: { ...apexBase(isDark).chart, type: 'line', height: 256 },
                    series: [],
                    yaxis: { labels: { style: { colors: labelColor }, formatter: v => v.toFixed(1) + '%' } },
                    tooltip: { x: { format: 'MMM d, yyyy' }, y: { formatter: v => v.toFixed(2) + '%' }, theme: isDark ? 'dark' : 'light' },
                });
                benchChart.render();

                function normalize(data) {
                    if (!data || data.length === 0) return [];
                    const base = data[0].price;
                    if (!base) return [];
                    return data.map(r => [new Date(r.date).getTime(), parseFloat(((r.price / base - 1) * 100).toFixed(2))]);
                }

                function buildPortfolioNorm(range) {
                    const cut = cutoffDate(range);
                    const filtered = cut ? allData.filter(r => new Date(r.date) >= cut) : allData;
                    if (!filtered.length) return [];
                    const base = filtered[0].value;
                    return filtered.map(r => [new Date(r.date).getTime(), parseFloat(((r.value / base - 1) * 100).toFixed(2))]);
                }

                function buildBenchNorm(ticker, range) {
                    const raw = benchRaw[ticker] || [];
                    const cut = cutoffDate(range);
                    const filtered = cut ? raw.filter(r => new Date(r.date) >= cut) : raw;
                    return normalize(filtered);
                }

                function updateBenchChart(range) {
                    const series = [
                        { name: 'My Portfolio', data: buildPortfolioNorm(range) },
                    ];
                    Object.keys(benchRaw).forEach(t => {
                        series.push({ name: t, data: buildBenchNorm(t, range) });
                    });
                    benchChart.updateSeries(series);
                    activateBtn('#bench-range-btns', 'button', range);
                }

                document.querySelectorAll('#bench-range-btns button').forEach(b =>
                    b.addEventListener('click', () => { benchRange = b.dataset.range; updateBenchChart(benchRange); })
                );
                updateBenchChart(benchRange);
            }

            // ── Allocation Donut ──────────────────────────────────────
            const donutEl = document.getElementById('allocationDonut');
            if (donutEl && allocData.total > 0) {
                const donutChart = new ApexCharts(donutEl, {
                    chart:   { type: 'donut', height: 256, background: 'transparent', toolbar: { show: false } },
                    theme:   { mode: isDark ? 'dark' : 'light' },
                    series:  allocData.values,
                    labels:  allocData.labels,
                    colors:  ['#6366f1', '#f97316', '#10b981'],
                    legend:  { show: false },
                    dataLabels: { enabled: true, formatter: (val) => val.toFixed(1) + '%' },
                    tooltip: { y: { formatter: v => '$' + v.toLocaleString('en-US', { minimumFractionDigits: 2 }) } },
                    plotOptions: { pie: { donut: { size: '65%' } } },
                });
                donutChart.render();
            }
        });
        </script>
        @endpush
    @endif
</x-app-layout>

<x-app-layout>
    @push('head-vite')
        @vite(['resources/js/chartjs.js'])
    @endpush

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Dividend Income</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (empty($years))
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-sm text-gray-500 dark:text-gray-400">
                    No dividend transactions recorded yet. Add a transaction with type <span class="font-mono">Dividend</span> from any portfolio's transaction form.
                </div>
            @else

                {{-- Year tabs --}}
                <div class="flex gap-2 flex-wrap">
                    @foreach ($years as $yr)
                        <a href="{{ route('dividends', ['year' => $yr]) }}"
                           class="px-4 py-1.5 rounded-full text-sm font-medium transition
                               {{ $yr === $selectedYear
                                  ? 'bg-indigo-600 text-white'
                                  : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:border-indigo-400 dark:hover:border-indigo-500' }}">
                            {{ $yr }}
                        </a>
                    @endforeach
                </div>

                {{-- Summary tiles --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $selectedYear }} Income</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100 font-mono">${{ number_format($totalIncome, 2) }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Tickers</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $totalTickers }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Payments</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $totalPayments }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400">All-Time Total</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100 font-mono">${{ number_format($allTimeTotal, 2) }}</p>
                    </div>
                </div>

                {{-- Monthly bar chart --}}
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Monthly Breakdown — {{ $selectedYear }}</h3>
                    <canvas id="dividendChart" height="100"></canvas>
                </div>

                {{-- Per-ticker table --}}
                @if ($byAsset->isNotEmpty())
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">By Ticker — {{ $selectedYear }}</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700/50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Symbol</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Payments</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Received</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">% of Year</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach ($byAsset as $row)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                            <td class="px-6 py-3 font-mono font-semibold text-gray-900 dark:text-gray-100">{{ $row['symbol'] }}</td>
                                            <td class="px-6 py-3 text-gray-500 dark:text-gray-400 capitalize">{{ str_replace('_', ' ', $row['asset_type']) }}</td>
                                            <td class="px-6 py-3 text-right text-gray-700 dark:text-gray-300">{{ $row['payments'] }}</td>
                                            <td class="px-6 py-3 text-right font-mono font-semibold text-gray-900 dark:text-gray-100">${{ number_format($row['total'], 2) }}</td>
                                            <td class="px-6 py-3 text-right text-gray-500 dark:text-gray-400">
                                                {{ $totalIncome > 0 ? number_format($row['total'] / $totalIncome * 100, 1) : '—' }}%
                                            </td>
                                        </tr>
                                    @endforeach
                                    <tr class="bg-gray-50 dark:bg-gray-700/40 font-semibold">
                                        <td class="px-6 py-3 text-gray-700 dark:text-gray-300" colspan="3">Total</td>
                                        <td class="px-6 py-3 text-right font-mono text-gray-900 dark:text-gray-100">${{ number_format($totalIncome, 2) }}</td>
                                        <td class="px-6 py-3 text-right text-gray-500 dark:text-gray-400">100%</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-sm text-gray-500 dark:text-gray-400">
                        No dividend transactions in {{ $selectedYear }}.
                    </div>
                @endif

            @endif
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const ctx = document.getElementById('dividendChart');
        if (!ctx) return; // no chart to draw in the "no dividends yet" empty state

        const monthData = @json(array_values($byMonth));
        const labels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        const isDark = document.documentElement.classList.contains('dark');
        const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
        const textColor = isDark ? '#9ca3af' : '#6b7280';

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Dividends',
                    data: monthData,
                    backgroundColor: 'rgba(99,102,241,0.7)',
                    borderRadius: 4,
                }],
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: { label: ctx => '$' + ctx.parsed.y.toFixed(2) },
                    },
                },
                scales: {
                    x: { grid: { color: gridColor }, ticks: { color: textColor } },
                    y: {
                        grid: { color: gridColor },
                        ticks: {
                            color: textColor,
                            callback: v => '$' + v.toFixed(0),
                        },
                        beginAtZero: true,
                    },
                },
            },
        });
    });
    </script>
    @endpush
</x-app-layout>

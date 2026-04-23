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

                {{-- Portfolio history chart --}}
                @if ($chartLabels->isNotEmpty())
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">Portfolio Value — Last 90 Days</h3>
                        <div class="relative h-64">
                            <canvas id="portfolioChart"></canvas>
                        </div>
                    </div>
                @endif

                {{-- All Holdings (aggregated across all portfolios) --}}
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

    @if ($chartLabels->isNotEmpty())
        @push('scripts')
        <script>
document.addEventListener("DOMContentLoaded", function () {
            const isDark = document.documentElement.classList.contains('dark');
            const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)';
            const tickColor = isDark ? 'rgb(156,163,175)' : 'rgb(107,114,128)';

            const ctx = document.getElementById('portfolioChart');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: @json($chartLabels),
                    datasets: [{
                        label: 'Total Value',
                        data: @json($chartData),
                        borderColor: isDark ? 'rgb(129,140,248)' : 'rgb(17, 24, 39)',
                        backgroundColor: isDark ? 'rgba(129,140,248,0.08)' : 'rgba(17, 24, 39, 0.05)',
                        borderWidth: 2,
                        pointRadius: 2,
                        fill: true,
                        tension: 0.3,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: ctx => '$' + ctx.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2 })
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: tickColor }
                        },
                        y: {
                            grid: { color: gridColor },
                            ticks: {
                                color: tickColor,
                                callback: val => '$' + val.toLocaleString('en-US', { minimumFractionDigits: 0 })
                            }
                        }
                    }
                }
            });
        });
</script>
        @endpush
    @endif
</x-app-layout>

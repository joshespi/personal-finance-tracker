<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $portfolio->name }}</h2>
                @if ($portfolio->description)
                    <p class="text-sm text-gray-500 mt-1">{{ $portfolio->description }}</p>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('portfolios.transactions.create', $portfolio) }}"
                   class="inline-flex items-center px-3 py-1.5 bg-gray-800 border border-transparent rounded-md text-xs font-semibold text-white hover:bg-gray-700 transition">
                    + Transaction
                </a>
                <a href="{{ route('portfolios.manual-assets.create', $portfolio) }}"
                   class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md text-xs font-semibold text-gray-700 hover:bg-gray-50 transition">
                    + Manual Asset
                </a>
                <a href="{{ route('portfolios.edit', $portfolio) }}"
                   class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md text-xs font-semibold text-gray-700 hover:bg-gray-50 transition">
                    Edit
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @if (session('success'))
                <div class="bg-green-100 border border-green-300 text-green-800 rounded-md px-4 py-3 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Portfolio Summary --}}
            @if ($holdings->isNotEmpty())
                @php
                    $totalCostBasis    = $holdings->sum('total_cost');
                    $holdingsWithPrice = $holdings->filter(fn($h) => $h['current_value'] !== null);
                    $totalCurrentValue = $holdingsWithPrice->sum('current_value');
                    $totalManualValue  = $portfolio->manualAssets->sum(fn($ma) => $ma->latestValuation ? (float)$ma->latestValuation->value : 0);
                    $totalUnrealized   = $holdingsWithPrice->sum('unrealized_gain');
                    $hasAnyPrice       = $holdingsWithPrice->isNotEmpty();
                @endphp
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-white shadow-sm sm:rounded-lg px-5 py-4">
                        <p class="text-xs text-gray-500 uppercase tracking-wide">Cost Basis</p>
                        <p class="mt-1 text-xl font-semibold font-mono text-gray-900">
                            {{ $portfolio->currency }} {{ number_format($totalCostBasis, 2) }}
                        </p>
                    </div>
                    @if ($hasAnyPrice)
                        <div class="bg-white shadow-sm sm:rounded-lg px-5 py-4">
                            <p class="text-xs text-gray-500 uppercase tracking-wide">Market Value</p>
                            <p class="mt-1 text-xl font-semibold font-mono text-gray-900">
                                {{ $portfolio->currency }} {{ number_format($totalCurrentValue, 2) }}
                            </p>
                        </div>
                        <div class="bg-white shadow-sm sm:rounded-lg px-5 py-4">
                            <p class="text-xs text-gray-500 uppercase tracking-wide">Unrealized P&L</p>
                            <p class="mt-1 text-xl font-semibold font-mono {{ $totalUnrealized >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $totalUnrealized >= 0 ? '+' : '' }}{{ $portfolio->currency }} {{ number_format($totalUnrealized, 2) }}
                            </p>
                        </div>
                    @endif
                    @if ($totalManualValue > 0)
                        <div class="bg-white shadow-sm sm:rounded-lg px-5 py-4">
                            <p class="text-xs text-gray-500 uppercase tracking-wide">Manual Assets</p>
                            <p class="mt-1 text-xl font-semibold font-mono text-gray-900">
                                {{ $portfolio->currency }} {{ number_format($totalManualValue, 2) }}
                            </p>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Historical Chart --}}
            @if ($chartData->count() > 1)
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="text-base font-semibold text-gray-900">Portfolio Value History</h3>
                    </div>
                    <div class="p-6">
                        <canvas id="portfolioChart" height="80"></canvas>
                    </div>
                </div>

                <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
                <script>
                    const chartData = @json($chartData);
                    const labels = chartData.map(d => d.date);
                    const values = chartData.map(d => d.value);
                    const costs  = chartData.map(d => d.cost);

                    new Chart(document.getElementById('portfolioChart'), {
                        type: 'line',
                        data: {
                            labels,
                            datasets: [
                                {
                                    label: 'Market Value',
                                    data: values,
                                    borderColor: 'rgb(99, 102, 241)',
                                    backgroundColor: 'rgba(99, 102, 241, 0.08)',
                                    fill: true,
                                    tension: 0.3,
                                    pointRadius: chartData.length > 60 ? 0 : 3,
                                },
                                {
                                    label: 'Cost Basis',
                                    data: costs,
                                    borderColor: 'rgb(156, 163, 175)',
                                    borderDash: [5, 5],
                                    fill: false,
                                    tension: 0.3,
                                    pointRadius: 0,
                                },
                            ],
                        },
                        options: {
                            responsive: true,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { position: 'bottom' },
                                tooltip: {
                                    callbacks: {
                                        label: ctx => ' $' + ctx.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2 }),
                                    },
                                },
                            },
                            scales: {
                                y: {
                                    ticks: {
                                        callback: val => '$' + val.toLocaleString('en-US', { minimumFractionDigits: 0 }),
                                    },
                                },
                            },
                        },
                    });
                </script>
            @endif

            {{-- Holdings --}}
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900">Holdings</h3>
                    <a href="{{ route('portfolios.transactions.index', $portfolio) }}"
                       class="text-sm text-indigo-600 hover:underline">View all transactions</a>
                </div>

                @if ($holdings->isEmpty())
                    <div class="p-6 text-sm text-gray-500">
                        No holdings yet.
                        <a href="{{ route('portfolios.transactions.create', $portfolio) }}" class="text-indigo-600 hover:underline">Add a buy transaction</a> to get started.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Symbol</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Cost</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Cost Basis</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Current Price</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Market Value</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Unrealized P&L</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @foreach ($holdings as $h)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-3 font-mono font-semibold text-gray-900">{{ $h['asset']->symbol }}</td>
                                        <td class="px-6 py-3 text-gray-500">{{ ucfirst($h['asset']->asset_type) }}</td>
                                        <td class="px-6 py-3 text-right font-mono text-gray-900">
                                            {{ rtrim(rtrim(number_format((float)$h['quantity'], 8), '0'), '.') }}
                                        </td>
                                        <td class="px-6 py-3 text-right font-mono text-gray-700">
                                            {{ $portfolio->currency }} {{ number_format((float)$h['avg_cost'], 4) }}
                                        </td>
                                        <td class="px-6 py-3 text-right font-mono font-semibold text-gray-900">
                                            {{ $portfolio->currency }} {{ number_format((float)$h['total_cost'], 2) }}
                                        </td>
                                        <td class="px-6 py-3 text-right font-mono text-gray-700">
                                            @if ($h['current_price'] !== null)
                                                {{ $portfolio->currency }} {{ number_format($h['current_price'], $h['asset']->asset_type === 'crypto' ? 4 : 2) }}
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-3 text-right font-mono text-gray-900">
                                            @if ($h['current_value'] !== null)
                                                {{ $portfolio->currency }} {{ number_format($h['current_value'], 2) }}
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-3 text-right font-mono">
                                            @if ($h['unrealized_gain'] !== null)
                                                <span class="{{ $h['unrealized_gain'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                    {{ $h['unrealized_gain'] >= 0 ? '+' : '' }}{{ number_format($h['unrealized_gain'], 2) }}
                                                    @if ($h['unrealized_pct'] !== null)
                                                        <span class="text-xs">({{ $h['unrealized_pct'] >= 0 ? '+' : '' }}{{ $h['unrealized_pct'] }}%)</span>
                                                    @endif
                                                </span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Dividend / Income --}}
            @if ($incomeByAsset->isNotEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="text-base font-semibold text-gray-900">Dividend / Income Received</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Symbol</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Income</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @foreach ($incomeByAsset as $inc)
                                    <tr>
                                        <td class="px-6 py-3 font-mono font-semibold text-gray-900">{{ $inc['asset']->symbol }}</td>
                                        <td class="px-6 py-3 text-right font-mono text-gray-700">
                                            {{ $portfolio->currency }} {{ number_format((float)$inc['total_income'], 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Manual Assets --}}
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900">Manual Assets</h3>
                    <a href="{{ route('portfolios.manual-assets.create', $portfolio) }}"
                       class="text-sm text-indigo-600 hover:underline">+ Add</a>
                </div>

                @if ($portfolio->manualAssets->isEmpty())
                    <div class="p-6 text-sm text-gray-500">No manual assets. Add real estate, vehicles, etc.</div>
                @else
                    <div class="divide-y divide-gray-100">
                        @foreach ($portfolio->manualAssets as $ma)
                            <div class="px-6 py-4 flex items-center justify-between">
                                <div>
                                    <a href="{{ route('manual-assets.show', $ma) }}"
                                       class="font-medium text-gray-900 hover:underline">{{ $ma->name }}</a>
                                    <p class="text-xs text-gray-500 mt-0.5">{{ ucwords(str_replace('_', ' ', $ma->asset_class)) }}</p>
                                </div>
                                <div class="text-right">
                                    @if ($ma->latestValuation)
                                        <p class="font-mono text-gray-900 text-sm">
                                            {{ $ma->currency }} {{ number_format((float)$ma->latestValuation->value, 2) }}
                                        </p>
                                        <p class="text-xs text-gray-400">as of {{ $ma->latestValuation->valued_at->format('M j, Y') }}</p>
                                    @else
                                        <span class="text-xs text-gray-400">No valuation yet</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>

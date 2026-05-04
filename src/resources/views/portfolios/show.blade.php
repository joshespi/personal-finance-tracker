<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ $portfolio->name }}</h2>
                @if ($portfolio->description)
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $portfolio->description }}</p>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('portfolios.transactions.create', $portfolio) }}"
                   class="inline-flex items-center px-3 py-1.5 bg-gray-800 dark:bg-gray-700 border border-transparent rounded-md text-xs font-semibold text-white hover:bg-gray-700 dark:hover:bg-gray-600 transition">
                    + Transaction
                </a>
                <a href="{{ route('portfolios.manual-assets.create', $portfolio) }}"
                   class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                    + Manual Asset
                </a>
                <a href="{{ route('portfolios.journal.index', $portfolio) }}"
                   class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                    Journal
                </a>
                <a href="{{ route('portfolios.edit', $portfolio) }}"
                   class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                    Edit
                </a>
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

            {{-- Summary stats --}}
            @if ($holdings->isNotEmpty())
                @php
                    $totalCostBasis    = $holdings->sum('total_cost');
                    $holdingsWithPrice = $holdings->filter(fn($h) => $h['current_value'] !== null);
                    $totalCurrentValue = $holdingsWithPrice->sum('current_value');
                    $totalManualValue  = $portfolio->manualAssets->sum(fn($ma) => $ma->latestValuation ? (float)$ma->latestValuation->value : 0);
                    $totalUnrealized   = $holdingsWithPrice->sum('unrealized_gain');
                    $hasAnyPrice       = $holdingsWithPrice->isNotEmpty();
                @endphp
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Cost Basis</p>
                        <p class="mt-1 text-xl font-semibold font-mono text-gray-900 dark:text-gray-100">
                            {{ $portfolio->currency }} {{ number_format($totalCostBasis, 2) }}
                        </p>
                    </div>
                    @if ($hasAnyPrice)
                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Market Value</p>
                            <p class="mt-1 text-xl font-semibold font-mono text-gray-900 dark:text-gray-100">
                                {{ $portfolio->currency }} {{ number_format($totalCurrentValue, 2) }}
                            </p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Unrealized P&L</p>
                            <p class="mt-1 text-xl font-semibold font-mono {{ $totalUnrealized >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $totalUnrealized >= 0 ? '+' : '' }}{{ $portfolio->currency }} {{ number_format($totalUnrealized, 2) }}
                            </p>
                        </div>
                    @endif
                    @if ($twr['total_pct'] !== null)
                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">TWR Return</p>
                            <p class="mt-1 text-xl font-semibold font-mono {{ $twr['total_pct'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $twr['total_pct'] >= 0 ? '+' : '' }}{{ $twr['total_pct'] }}%
                            </p>
                            @if ($twr['annualized_pct'] !== null)
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                    {{ $twr['annualized_pct'] >= 0 ? '+' : '' }}{{ $twr['annualized_pct'] }}% ann.
                                </p>
                            @endif
                        </div>
                    @endif
                    @if ($realizedGains['totalGain'] != 0)
                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Realized P&L</p>
                            <p class="mt-1 text-xl font-semibold font-mono {{ $realizedGains['totalGain'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $realizedGains['totalGain'] >= 0 ? '+' : '' }}{{ $portfolio->currency }} {{ number_format($realizedGains['totalGain'], 2) }}
                            </p>
                        </div>
                    @endif
                    @if ($totalManualValue > 0)
                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Manual Assets</p>
                            <p class="mt-1 text-xl font-semibold font-mono text-gray-900 dark:text-gray-100">
                                {{ $portfolio->currency }} {{ number_format($totalManualValue, 2) }}
                            </p>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Value history chart with time range toggles --}}
            @if ($chartData->count() > 1)
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Portfolio Value History</h3>
                        <div class="flex flex-wrap gap-1" id="port-range-btns">
                            @foreach (['5D','1W','1M','3M','6M','1Y','YTD','5Y','10Y','All'] as $r)
                                <button data-range="{{ $r }}"
                                        class="px-2.5 py-1 text-xs rounded font-medium transition
                                               bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300
                                               hover:bg-gray-200 dark:hover:bg-gray-600">
                                    {{ $r }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <div class="h-72 relative"><canvas id="portChart"></canvas></div>
                </div>

                {{-- Benchmark comparison --}}
                @if (!empty($benchmarkData))
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Benchmark Comparison</h3>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Normalized to 100 — relative % return</p>
                            </div>
                            <div class="flex flex-wrap gap-1" id="port-bench-range-btns">
                                @foreach (['1M','3M','6M','1Y','5Y','10Y','All'] as $r)
                                    <button data-range="{{ $r }}"
                                            class="px-2.5 py-1 text-xs rounded font-medium transition
                                                   bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300
                                                   hover:bg-gray-200 dark:hover:bg-gray-600">
                                        {{ $r }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        <div class="h-64 relative"><canvas id="portBenchChart"></canvas></div>
                    </div>
                @endif
            @endif

            {{-- Asset allocation donut --}}
            @if ($allocation['total'] > 0)
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">Allocation</h3>
                    <div class="flex flex-col sm:flex-row items-center gap-8">
                        <div class="w-64 h-64 shrink-0 relative"><canvas id="portAllocationDonut"></canvas></div>
                        <div class="space-y-2 text-sm w-full max-w-sm">
                            @foreach ($allocation['holdings'] as $h)
                                @php
                                    $legendColor = match ($h['type']) {
                                        'crypto'      => 'text-orange-500',
                                        'real_estate' => 'text-emerald-500',
                                        default       => 'text-blue-500',
                                    };
                                    $legendLabel = $h['type'] === 'real_estate' ? 'Real Estate' : ucfirst($h['type']);
                                @endphp
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono font-semibold text-gray-900 dark:text-gray-100 w-16">{{ $h['symbol'] }}</span>
                                        <span class="text-xs {{ $legendColor }}">{{ $legendLabel }}</span>
                                    </div>
                                    <div class="flex items-center gap-4 text-right">
                                        <span class="font-mono text-gray-900 dark:text-gray-100">${{ number_format($h['value'], 2) }}</span>
                                        <span class="text-gray-400 dark:text-gray-500 w-12">
                                            {{ $allocation['total'] > 0 ? number_format($h['value'] / $allocation['total'] * 100, 1) : 0 }}%
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                            @if ($allocation['manual_value'] > 0)
                                <div class="flex items-center justify-between pt-1 border-t border-gray-100 dark:border-gray-700">
                                    <span class="text-gray-600 dark:text-gray-400">Manual Assets</span>
                                    <div class="flex items-center gap-4 text-right">
                                        <span class="font-mono text-gray-900 dark:text-gray-100">${{ number_format($allocation['manual_value'], 2) }}</span>
                                        <span class="text-gray-400 dark:text-gray-500 w-12">
                                            {{ $allocation['total'] > 0 ? number_format($allocation['manual_value'] / $allocation['total'] * 100, 1) : 0 }}%
                                        </span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            {{-- Holdings --}}
            @php
                $incomeMap = $incomeByAsset->keyBy(fn ($i) => $i['asset']->id);
                $holdingsRows = $holdings->map(function ($h) use ($incomeMap) {
                    $income      = $incomeMap->get($h['asset']->id);
                    $totalIncome = $income ? (float) $income['total_income'] : null;
                    $yoc         = ($totalIncome !== null && (float) $h['total_cost'] > 0)
                        ? round($totalIncome / (float) $h['total_cost'] * 100, 2)
                        : null;
                    $currYield   = ($totalIncome !== null && $h['current_value'] !== null && (float) $h['current_value'] > 0)
                        ? round($totalIncome / (float) $h['current_value'] * 100, 2)
                        : null;
                    return [
                        'symbol'          => $h['asset']->symbol,
                        'asset_type'      => $h['asset']->asset_type,
                        'asset_id'        => $h['asset']->id,
                        'quantity'        => (float) $h['quantity'],
                        'avg_cost'        => (float) $h['avg_cost'],
                        'total_cost'      => (float) $h['total_cost'],
                        'current_price'   => $h['current_price'] !== null ? (float) $h['current_price'] : null,
                        'current_value'   => $h['current_value'] !== null ? (float) $h['current_value'] : null,
                        'unrealized_gain' => $h['unrealized_gain'] !== null ? (float) $h['unrealized_gain'] : null,
                        'unrealized_pct'  => $h['unrealized_pct'] !== null ? (float) $h['unrealized_pct'] : null,
                        'sort_value'      => (float) ($h['current_value'] ?? $h['total_cost']),
                        'total_income'    => $totalIncome,
                        'yield_on_cost'   => $yoc,
                        'current_yield'   => $currYield,
                        'reclassify_url'  => route('assets.reclassify', $h['asset']),
                    ];
                })->values()->all();
                $ccy = $portfolio->currency;
            @endphp
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg"
                 x-data="holdingsSort({{ json_encode($holdingsRows) }})">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Holdings</h3>
                    <a href="{{ route('portfolios.transactions.index', $portfolio) }}"
                       class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">View all transactions</a>
                </div>

                @if ($holdings->isEmpty())
                    <div class="p-6 text-sm text-gray-500 dark:text-gray-400">
                        No holdings yet.
                        <a href="{{ route('portfolios.transactions.create', $portfolio) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Add a buy transaction</a> to get started.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th @click="sort('symbol')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 select-none">
                                        Symbol<span x-text="arrow('symbol')"></span>
                                    </th>
                                    <th @click="sort('asset_type')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 select-none">
                                        Type<span x-text="arrow('asset_type')"></span>
                                    </th>
                                    <th @click="sort('quantity')" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 select-none">
                                        Quantity<span x-text="arrow('quantity')"></span>
                                    </th>
                                    <th @click="sort('avg_cost')" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 select-none">
                                        Avg Cost<span x-text="arrow('avg_cost')"></span>
                                    </th>
                                    <th @click="sort('total_cost')" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 select-none">
                                        Cost Basis<span x-text="arrow('total_cost')"></span>
                                    </th>
                                    <th @click="sort('current_price')" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 select-none">
                                        Current Price<span x-text="arrow('current_price')"></span>
                                    </th>
                                    <th @click="sort('sort_value')" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 select-none">
                                        Market Value<span x-text="arrow('sort_value')"></span>
                                    </th>
                                    <th @click="sort('unrealized_gain')" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 select-none">
                                        Unrealized P&L<span x-text="arrow('unrealized_gain')"></span>
                                    </th>
                                    <th @click="sort('total_income')" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 select-none">
                                        Income<span x-text="arrow('total_income')"></span>
                                    </th>
                                    <th @click="sort('yield_on_cost')" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 select-none">
                                        YOC<span x-text="arrow('yield_on_cost')"></span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                                <template x-for="h in sorted" :key="h.asset_id">
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-6 py-3 font-mono font-semibold text-gray-900 dark:text-gray-100" x-text="h.symbol"></td>
                                        <td class="px-6 py-3">
                                            <form :action="h.reclassify_url" method="POST" class="inline">
                                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                                <input type="hidden" name="_method" value="PATCH">
                                                <select name="asset_type"
                                                        @change="$el.form.submit()"
                                                        title="Reclassify"
                                                        class="text-xs font-medium border-0 rounded px-2 py-0.5 cursor-pointer focus:ring-1 focus:ring-indigo-500"
                                                        :class="h.asset_type === 'crypto'
                                                            ? 'bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300'
                                                            : (h.asset_type === 'real_estate'
                                                                ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300'
                                                                : 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300')">
                                                    <option value="stock"       :selected="h.asset_type === 'stock'">Stock</option>
                                                    <option value="crypto"      :selected="h.asset_type === 'crypto'">Crypto</option>
                                                    <option value="real_estate" :selected="h.asset_type === 'real_estate'">Real Estate</option>
                                                </select>
                                            </form>
                                        </td>
                                        <td class="px-6 py-3 text-right font-mono text-gray-900 dark:text-gray-100" x-text="fmtQty(h.quantity)"></td>
                                        <td class="px-6 py-3 text-right font-mono text-gray-700 dark:text-gray-300" x-text="'{{ $ccy }} ' + fmtPrice(h.avg_cost).replace('$','')"></td>
                                        <td class="px-6 py-3 text-right font-mono font-semibold text-gray-900 dark:text-gray-100" x-text="'{{ $ccy }} ' + fmtMoney(h.total_cost).replace('$','')"></td>
                                        <td class="px-6 py-3 text-right font-mono text-gray-700 dark:text-gray-300"
                                            x-text="h.current_price !== null ? '{{ $ccy }} ' + fmtPrice(h.current_price).replace('$','') : '—'"></td>
                                        <td class="px-6 py-3 text-right font-mono text-gray-900 dark:text-gray-100"
                                            x-text="h.current_value !== null ? '{{ $ccy }} ' + fmtMoney(h.current_value).replace('$','') : '—'"></td>
                                        <td class="px-6 py-3 text-right font-mono" :class="plClass(h.unrealized_gain)">
                                            <span x-show="h.unrealized_gain !== null">
                                                <span x-text="plFmt(h.unrealized_gain)"></span>
                                                <span x-show="h.unrealized_pct !== null" class="text-xs"
                                                      x-text="' (' + (h.unrealized_pct >= 0 ? '+' : '') + h.unrealized_pct?.toFixed(2) + '%)'"></span>
                                            </span>
                                            <span x-show="h.unrealized_gain === null" class="text-gray-300 dark:text-gray-600">—</span>
                                        </td>
                                        <td class="px-6 py-3 text-right font-mono text-gray-700 dark:text-gray-300"
                                            x-text="h.total_income !== null ? fmtMoney(h.total_income) : '—'"></td>
                                        <td class="px-6 py-3 text-right font-mono text-gray-700 dark:text-gray-300"
                                            x-text="h.yield_on_cost !== null ? h.yield_on_cost.toFixed(2) + '%' : '—'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Realized P&L --}}
            @if ($realizedGains['lots']->isNotEmpty())
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Realized Gains / Losses (FIFO)</h3>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Closed positions only</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-gray-400 dark:text-gray-500">Total Realized</p>
                            <p class="font-mono font-semibold text-lg {{ $realizedGains['totalGain'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $realizedGains['totalGain'] >= 0 ? '+' : '' }}{{ $portfolio->currency }} {{ number_format($realizedGains['totalGain'], 2) }}
                            </p>
                        </div>
                    </div>

                    {{-- By year summary --}}
                    @if (!empty($realizedGains['byYear']))
                        <div class="px-6 py-3 border-b border-gray-100 dark:border-gray-700 flex flex-wrap gap-6">
                            @foreach ($realizedGains['byYear'] as $year => $gain)
                                <div>
                                    <p class="text-xs text-gray-400 dark:text-gray-500">{{ $year }}</p>
                                    <p class="font-mono font-semibold text-sm {{ $gain >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $gain >= 0 ? '+' : '' }}${{ number_format($gain, 2) }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- By asset --}}
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Symbol</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Buy Date</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Sell Date</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Qty</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cost Basis</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Proceeds</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Gain / Loss</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Days Held</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($realizedGains['lots'] as $lot)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-6 py-3 font-mono font-semibold text-gray-900 dark:text-gray-100">{{ $lot['asset']->symbol }}</td>
                                        <td class="px-6 py-3 text-right text-gray-500 dark:text-gray-400">{{ $lot['buy_date']->format('Y-m-d') }}</td>
                                        <td class="px-6 py-3 text-right text-gray-500 dark:text-gray-400">{{ $lot['sell_date']->format('Y-m-d') }}</td>
                                        <td class="px-6 py-3 text-right font-mono text-gray-700 dark:text-gray-300">
                                            {{ rtrim(rtrim(number_format((float)$lot['quantity'], 8), '0'), '.') }}
                                        </td>
                                        <td class="px-6 py-3 text-right font-mono text-gray-700 dark:text-gray-300">${{ number_format($lot['cost_basis'], 2) }}</td>
                                        <td class="px-6 py-3 text-right font-mono text-gray-700 dark:text-gray-300">${{ number_format($lot['proceeds'], 2) }}</td>
                                        <td class="px-6 py-3 text-right font-mono font-semibold {{ $lot['gain'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $lot['gain'] >= 0 ? '+' : '' }}${{ number_format($lot['gain'], 2) }}
                                        </td>
                                        <td class="px-6 py-3 text-right font-mono text-gray-500 dark:text-gray-400">
                                            {{ number_format($lot['holding_days']) }}
                                            @if ($lot['holding_days'] >= 365)
                                                <span class="text-xs text-green-500 ml-1">LT</span>
                                            @else
                                                <span class="text-xs text-amber-500 ml-1">ST</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Rebalancing --}}
            @if (!empty($rebalancing))
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Rebalancing</h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                            Target allocations set in
                            <a href="{{ route('portfolios.edit', $portfolio) }}" class="text-indigo-500 hover:underline">portfolio settings</a>
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Asset Class</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Current Value</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Current %</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Target %</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Drift</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($rebalancing as $row)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-6 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $row['label'] }}</td>
                                        <td class="px-6 py-3 text-right font-mono text-gray-700 dark:text-gray-300">${{ number_format($row['current_val'], 2) }}</td>
                                        <td class="px-6 py-3 text-right font-mono text-gray-700 dark:text-gray-300">{{ $row['current_pct'] }}%</td>
                                        <td class="px-6 py-3 text-right font-mono text-gray-700 dark:text-gray-300">{{ $row['target_pct'] }}%</td>
                                        <td class="px-6 py-3 text-right font-mono font-semibold {{ abs($row['current_pct'] - $row['target_pct']) < 2 ? 'text-gray-400' : ($row['current_pct'] > $row['target_pct'] ? 'text-red-500' : 'text-amber-500') }}">
                                            {{ $row['current_pct'] >= $row['target_pct'] ? '+' : '' }}{{ number_format($row['current_pct'] - $row['target_pct'], 1) }}%
                                        </td>
                                        <td class="px-6 py-3 text-right font-mono font-semibold">
                                            @if (abs($row['diff']) < 1)
                                                <span class="text-green-500 text-xs">Balanced</span>
                                            @elseif ($row['diff'] > 0)
                                                <span class="text-green-600">Buy ${{ number_format($row['diff'], 2) }}</span>
                                            @else
                                                <span class="text-red-500">Sell ${{ number_format(abs($row['diff']), 2) }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Dividend / Income --}}
            @if ($incomeByAsset->isNotEmpty())
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Dividend / Income Received</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Symbol</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total Income</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($incomeByAsset as $inc)
                                    <tr>
                                        <td class="px-6 py-3 font-mono font-semibold text-gray-900 dark:text-gray-100">{{ $inc['asset']->symbol }}</td>
                                        <td class="px-6 py-3 text-right font-mono text-gray-700 dark:text-gray-300">
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
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Manual Assets</h3>
                    <a href="{{ route('portfolios.manual-assets.create', $portfolio) }}"
                       class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">+ Add</a>
                </div>

                @if ($portfolio->manualAssets->isEmpty())
                    <div class="p-6 text-sm text-gray-500 dark:text-gray-400">No manual assets. Add real estate, vehicles, etc.</div>
                @else
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($portfolio->manualAssets as $ma)
                            <div class="px-6 py-4 flex items-center justify-between">
                                <div>
                                    <a href="{{ route('manual-assets.show', $ma) }}"
                                       class="font-medium text-gray-900 dark:text-gray-100 hover:underline">{{ $ma->name }}</a>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ ucwords(str_replace('_', ' ', $ma->asset_class)) }}</p>
                                </div>
                                <div class="text-right">
                                    @if ($ma->latestValuation)
                                        <p class="font-mono text-gray-900 dark:text-gray-100 text-sm">
                                            {{ $ma->currency }} {{ number_format((float)$ma->latestValuation->value, 2) }}
                                        </p>
                                        <p class="text-xs text-gray-400 dark:text-gray-500">as of {{ $ma->latestValuation->valued_at->format('M j, Y') }}</p>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-500">No valuation yet</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>
    </div>

    @if ($chartData->count() > 1)
        @vite('resources/js/chartjs.js')
        @push('scripts')
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const isDark     = document.documentElement.classList.contains('dark');
            const gridColor  = isDark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.07)';
            const labelColor = isDark ? '#9ca3af' : '#6b7280';
            const allData    = @json($chartData);       // [{date, value, cost}]
            const benchRaw   = @json($benchmarkData);   // {SPY: [...], BTC: [...]}
            const allocData  = @json($allocation);

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
                    default:    return null;
                }
            }

            function filterByRange(data, range) {
                const cut = cutoffDate(range);
                return cut ? data.filter(r => new Date(r.date) >= cut) : data;
            }

            function activateBtn(containerSel, activeRange) {
                document.querySelectorAll(containerSel + ' button').forEach(b => {
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

            function fmtK(v) {
                const abs = Math.abs(v);
                if (abs >= 1e6) return '$' + (v / 1e6).toFixed(2) + 'M';
                if (abs >= 1e3) return '$' + (v / 1e3).toFixed(1) + 'K';
                return '$' + v.toFixed(0);
            }

            function fmtFull(v) {
                const abs = Math.abs(v);
                const str = abs.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                return (v < 0 ? '-$' : '$') + str;
            }

            function timeSeriesScales(yFormatter) {
                return {
                    x: {
                        type: 'time',
                        time: { unit: 'day', tooltipFormat: 'MMM d, yyyy' },
                        grid: { color: gridColor },
                        ticks: { color: labelColor },
                    },
                    y: {
                        grid: { color: gridColor },
                        ticks: { color: labelColor, callback: yFormatter },
                    },
                };
            }

            function legendOpts() {
                return { display: true, position: 'bottom', labels: { color: labelColor } };
            }

            function pointFromRow(r, key) {
                return { x: new Date(r.date).getTime(), y: r[key] };
            }

            // ── Portfolio Value Chart ─────────────────────────────────
            let portRange = '1Y';
            const portChart = new Chart(document.getElementById('portChart'), {
                type: 'line',
                data: {
                    datasets: [
                        { label: 'Market Value', data: [], borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.15)', fill: true, tension: 0.3, borderWidth: 2, pointRadius: 0 },
                        { label: 'Cost Basis',   data: [], borderColor: '#94a3b8', borderDash: [5, 5], fill: false, tension: 0.3, borderWidth: 2, pointRadius: 0 },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: timeSeriesScales(fmtK),
                    plugins: {
                        legend: legendOpts(),
                        tooltip: { callbacks: { label: ctx => `${ctx.dataset.label}: ${fmtFull(ctx.parsed.y)}` } },
                    },
                },
            });

            function updatePortChart(range) {
                const filtered = filterByRange(allData, range);
                portChart.data.datasets[0].data = filtered.map(r => pointFromRow(r, 'value'));
                portChart.data.datasets[1].data = filtered.map(r => pointFromRow(r, 'cost'));
                portChart.update();
                activateBtn('#port-range-btns', range);
            }

            document.querySelectorAll('#port-range-btns button').forEach(b =>
                b.addEventListener('click', () => { portRange = b.dataset.range; updatePortChart(portRange); })
            );
            updatePortChart(portRange);

            // ── Benchmark Chart ───────────────────────────────────────
            const benchEl = document.getElementById('portBenchChart');
            if (benchEl && Object.keys(benchRaw).length > 0) {
                let benchRange = '1Y';
                const benchColors = { 'This Portfolio': '#6366f1', SPY: '#10b981', BTC: '#f59e0b' };

                function buildPortNorm(range) {
                    const f = filterByRange(allData, range);
                    if (!f.length) return [];
                    const base = f[0].value;
                    return f.map(r => ({ x: new Date(r.date).getTime(), y: parseFloat(((r.value / base - 1) * 100).toFixed(2)) }));
                }

                function buildBenchNorm(ticker, range) {
                    const raw = benchRaw[ticker] || [];
                    const cut = cutoffDate(range);
                    const f   = cut ? raw.filter(r => new Date(r.date) >= cut) : raw;
                    if (!f.length) return [];
                    const base = f[0].price;
                    return f.map(r => ({ x: new Date(r.date).getTime(), y: parseFloat(((r.price / base - 1) * 100).toFixed(2)) }));
                }

                const benchChart = new Chart(benchEl, {
                    type: 'line',
                    data: { datasets: [] },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        scales: timeSeriesScales(v => v.toFixed(1) + '%'),
                        plugins: {
                            legend: legendOpts(),
                            tooltip: { callbacks: { label: ctx => `${ctx.dataset.label}: ${ctx.parsed.y.toFixed(2)}%` } },
                        },
                    },
                });

                function updateBenchChart(range) {
                    const datasets = [{ label: 'This Portfolio', data: buildPortNorm(range), borderColor: benchColors['This Portfolio'], fill: false, tension: 0.3, borderWidth: 2, pointRadius: 0 }];
                    Object.keys(benchRaw).forEach(t => datasets.push({
                        label: t,
                        data: buildBenchNorm(t, range),
                        borderColor: benchColors[t] || '#9ca3af',
                        fill: false,
                        tension: 0.3,
                        borderWidth: 2,
                        pointRadius: 0,
                    }));
                    benchChart.data.datasets = datasets;
                    benchChart.update();
                    activateBtn('#port-bench-range-btns', range);
                }

                document.querySelectorAll('#port-bench-range-btns button').forEach(b =>
                    b.addEventListener('click', () => { benchRange = b.dataset.range; updateBenchChart(benchRange); })
                );
                updateBenchChart(benchRange);
            }

            // ── Allocation Pie ────────────────────────────────────────
            const donutEl = document.getElementById('portAllocationDonut');
            if (donutEl && allocData.total > 0) {
                const allLabels = allocData.holdings.map(h => h.symbol);
                const allValues = allocData.holdings.map(h => h.value);

                if (allocData.manual_value > 0) {
                    allLabels.push('Manual');
                    allValues.push(allocData.manual_value);
                }

                new Chart(donutEl, {
                    type: 'pie',
                    data: { labels: allLabels, datasets: [{ data: allValues, borderWidth: 0 }] },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: ctx => `${ctx.label}: $${ctx.parsed.toLocaleString('en-US', { minimumFractionDigits: 2 })}`,
                                },
                            },
                        },
                    },
                });
            }
        });
        </script>
        @endpush
    @endif
</x-app-layout>

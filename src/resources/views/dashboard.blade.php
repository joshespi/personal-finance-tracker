<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Dashboard</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @php
                $hasPortfolios = ! $summaries->isEmpty();
                $hasMoneyData  = $totals['total_value'] > 0 || $totals['total_debt'] > 0;
                $monthlySpend  = $budgetRuleData['monthly_mandatory'] + $budgetRuleData['monthly_discretionary'];
            @endphp

            <x-budget-rule-drift-banner :drift="$budgetRuleData['drift']" :ratios="$budgetRuleData['ratios']" />

            @if ($revolvingBalance > 0)
                <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg px-5 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-sm text-amber-800 dark:text-amber-300">
                    <div>
                        <span class="font-semibold">Interest bleed:</span>
                        <span class="font-mono">${{ number_format($interestBleedMonthly, 2) }}/mo &middot; ${{ number_format($interestBleedYearly, 2) }}/yr</span>
                        draining to lenders.
                    </div>
                    <a href="{{ route('debt-payoff') }}" class="shrink-0 inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-800 border border-amber-300 dark:border-amber-600 rounded-md text-xs font-semibold text-amber-800 dark:text-amber-200 hover:bg-amber-100 dark:hover:bg-amber-900/40 transition">
                        View payoff plan &rarr;
                    </a>
                </div>
            @endif

            @if (! $hasPortfolios && ! $hasMoneyData)
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-8 text-center">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Welcome to your financial tracker</h3>
                    <p class="text-gray-500 dark:text-gray-400 mb-6">Get started by adding any of the following:</p>
                    <div class="flex flex-wrap justify-center gap-3">
                        <a href="{{ route('portfolios.create') }}"
                           class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-700 rounded-md text-sm font-semibold text-white hover:bg-gray-700 dark:hover:bg-gray-600 transition">
                            Create a portfolio
                        </a>
                        <a href="{{ route('cash-accounts.create') }}"
                           class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                            Add a spending account
                        </a>
                        <a href="{{ route('liabilities.create') }}"
                           class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                            Track a debt
                        </a>
                        <a href="{{ route('envelopes.create') }}"
                           class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                            Set up budget envelopes
                        </a>
                    </div>
                </div>
            @else

                @if ($hasPortfolios)
                    {{-- Section 1: Investment Portfolio --}}
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">Investment Portfolio</p>
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                            <x-stat-tile>
                                <x-slot:label>Tracked Assets</x-slot:label>
                                <p id="tile-total-value" class="mt-1 text-2xl font-semibold font-mono text-gray-900 dark:text-gray-100">
                                    ${{ number_format($totals['portfolio_value'], 2) }}
                                </p>
                            </x-stat-tile>
                            <x-stat-tile>
                                <x-slot:label>Cost Basis</x-slot:label>
                                <p class="mt-1 text-2xl font-semibold font-mono text-gray-900 dark:text-gray-100">
                                    ${{ number_format($totals['cost_basis'], 2) }}
                                </p>
                            </x-stat-tile>
                            @if ($totals['market_value'] !== null)
                                @php $unr = $totals['unrealized'] ?? 0; @endphp
                                <x-stat-tile>
                                    <x-slot:label>Market Value</x-slot:label>
                                    <p id="tile-market-value" class="mt-1 text-2xl font-semibold font-mono text-gray-900 dark:text-gray-100">
                                        ${{ number_format($totals['market_value'], 2) }}
                                    </p>
                                </x-stat-tile>
                                <x-stat-tile>
                                    <x-slot:label>Unrealized P&L</x-slot:label>
                                    <p class="mt-1 text-2xl font-semibold font-mono {{ $unr >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $unr >= 0 ? '+$' : '-$' }}{{ number_format(abs($unr), 2) }}
                                    </p>
                                </x-stat-tile>
                                <x-stat-tile>
                                    <x-slot:label><span id="tile-pl-label">1Y Gain/Loss</span></x-slot:label>
                                    <p id="tile-pl-value" class="mt-1 text-2xl font-semibold font-mono {{ $unr >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $unr >= 0 ? '+$' : '-$' }}{{ number_format(abs($unr), 2) }}
                                    </p>
                                </x-stat-tile>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($hasMoneyData)
                    {{-- Section 2: Full Financial Picture --}}
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">Full Financial Picture</p>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <x-stat-tile>
                                <x-slot:label>Cash Balance</x-slot:label>
                                <p class="mt-1 text-2xl font-semibold font-mono text-gray-900 dark:text-gray-100">
                                    ${{ number_format($totalCash, 2) }}
                                </p>
                            </x-stat-tile>
                            <x-stat-tile>
                                <x-slot:label>Total Assets</x-slot:label>
                                <p class="mt-1 text-2xl font-semibold font-mono text-gray-900 dark:text-gray-100">
                                    ${{ number_format($totals['total_value'], 2) }}
                                </p>
                            </x-stat-tile>
                            <x-stat-tile>
                                <x-slot:label><a href="{{ route('liabilities.index') }}" class="hover:underline">Total Debt</a></x-slot:label>
                                <p class="mt-1 text-2xl font-semibold font-mono {{ $totals['total_debt'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100' }}">
                                    {{ $totals['total_debt'] > 0 ? '−' : '' }}${{ number_format($totals['total_debt'], 2) }}
                                </p>
                            </x-stat-tile>
                            <x-stat-tile :highlight="true">
                                <x-slot:label>Net Worth</x-slot:label>
                                <p class="mt-1 text-2xl font-semibold font-mono {{ $totals['net_worth'] >= 0 ? 'text-gray-900 dark:text-gray-100' : 'text-red-600' }}">
                                    {{ $totals['net_worth'] < 0 ? '−' : '' }}${{ number_format(abs($totals['net_worth']), 2) }}
                                </p>
                            </x-stat-tile>
                            @if ($totals['debt_to_asset'] !== null)
                                <x-stat-tile>
                                    <x-slot:label>Debt-to-Asset</x-slot:label>
                                    <p class="mt-1 text-2xl font-semibold font-mono text-gray-900 dark:text-gray-100">
                                        {{ $totals['debt_to_asset'] }}%
                                    </p>
                                </x-stat-tile>
                            @endif
                            @if ($budgetRuleData['emergency_target'] > 0)
                                @php
                                    $efMonths = round(
                                        $budgetRuleData['emergency_balance'] * $budgetRuleData['target_months'] / $budgetRuleData['emergency_target'],
                                        1
                                    );
                                @endphp
                                <x-stat-tile>
                                    <x-slot:label>Emergency Fund</x-slot:label>
                                    <p class="mt-1 text-2xl font-semibold font-mono {{ $budgetRuleData['emergency_funded'] ? 'text-green-600' : 'text-amber-500' }}">
                                        {{ $efMonths }}<span class="text-sm font-normal text-gray-500 dark:text-gray-400"> / {{ $budgetRuleData['target_months'] }} mo</span>
                                    </p>
                                </x-stat-tile>
                            @endif
                            <x-stat-tile>
                                <x-slot:label><a href="{{ route('ready-to-assign') }}" class="hover:underline">Ready to Assign</a></x-slot:label>
                                <p class="mt-1 text-2xl font-semibold font-mono {{ $readyToAssign >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $readyToAssign < 0 ? '−' : '' }}${{ number_format(abs($readyToAssign), 2) }}
                                </p>
                            </x-stat-tile>
                            @if ($budgetRuleData['has_data'])
                                <x-stat-tile>
                                    <x-slot:label>Monthly Spend</x-slot:label>
                                    <p class="mt-1 text-2xl font-semibold font-mono text-gray-900 dark:text-gray-100">
                                        ${{ number_format($monthlySpend, 2) }}
                                    </p>
                                </x-stat-tile>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($chartData->count() > 1)
                    {{-- Portfolio value chart with time range toggles --}}
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                            <div class="flex items-center gap-3">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Portfolio Value</h3>
                                <button id="manual-toggle"
                                        class="px-2 py-0.5 text-xs rounded font-medium transition border"
                                        title="Toggle manual assets in chart">
                                    Manual
                                </button>
                            </div>
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
                        <div class="h-64 relative"><canvas id="dashChart"></canvas></div>
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
                            <div class="h-64 relative"><canvas id="benchmarkChart"></canvas></div>
                        </div>
                    @endif
                @endif

                {{-- Asset allocation donut --}}
                @if ($allocation['total'] > 0)
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">Asset Allocation</h3>
                        <div class="flex flex-col sm:flex-row items-center gap-8">
                            <div class="w-56 h-56 sm:w-64 sm:h-64 mx-auto sm:mx-0 shrink-0 relative"><canvas id="allocationDonut"></canvas></div>
                            <div class="space-y-2 text-sm">
                                @foreach ($allocation['labels'] as $i => $label)
                                    @php $val = $allocation['values'][$i]; @endphp
                                    @if ($val > 0)
                                        <div class="flex items-center gap-3">
                                            <span class="w-3 h-3 rounded-full shrink-0" style="background:{{ $allocation['colors'][$i] }}"></span>
                                            <span class="text-gray-700 dark:text-gray-300 min-w-[5rem]">{{ $label }}</span>
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

                {{-- Global rebalancing --}}
                @if (!empty($rebalancing))
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Global Rebalancing</h3>
                            <a href="{{ route('profile.edit') }}#investment-targets"
                               class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Edit targets</a>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="text-xs text-gray-500 dark:text-gray-400 uppercase">
                                        <th class="text-left py-2 pr-4">Class</th>
                                        <th class="text-right py-2 px-4">Current</th>
                                        <th class="text-right py-2 px-4">Target</th>
                                        <th class="text-right py-2 px-4">Drift</th>
                                        <th class="text-right py-2 pl-4">To Buy / Sell</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach ($rebalancing as $row)
                                        <tr>
                                            <td class="py-2 pr-4 font-medium text-gray-900 dark:text-gray-100">{{ $row['label'] }}</td>
                                            <td class="py-2 px-4 text-right font-mono text-gray-700 dark:text-gray-300">
                                                ${{ number_format($row['current_val'], 0) }}
                                                <span class="text-gray-400">({{ $row['current_pct'] }}%)</span>
                                            </td>
                                            <td class="py-2 px-4 text-right font-mono text-gray-500 dark:text-gray-400">
                                                {{ $row['target_pct'] }}%
                                            </td>
                                            <td class="py-2 px-4 text-right font-mono {{ abs($row['drift_pct']) >= 3 ? ($row['drift_pct'] < 0 ? 'text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-400') : 'text-gray-400' }}">
                                                {{ $row['drift_pct'] > 0 ? '+' : '' }}{{ number_format($row['drift_pct'], 1) }}%
                                            </td>
                                            <td class="py-2 pl-4 text-right font-mono {{ $row['diff'] < 0 ? 'text-red-600 dark:text-red-400' : ($row['diff'] > 0 ? 'text-green-600 dark:text-green-400' : 'text-gray-400') }}">
                                                {{ $row['diff'] >= 0 ? '+' : '' }}${{ number_format(abs($row['diff']), 0) }}
                                                <span class="text-xs text-gray-400">{{ $row['diff'] > 0 ? 'buy' : ($row['diff'] < 0 ? 'sell' : '—') }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- All Holdings --}}
                @if ($allHoldings->isNotEmpty())
                    @php
                        $holdingsRows = $allHoldings->map(fn ($h) => [
                            'symbol'          => $h['asset']->symbol,
                            'asset_type'      => $h['asset']->asset_type,
                            'price_source'    => $h['asset']->price_source,
                            'asset_id'        => $h['asset']->id,
                            'quantity'        => (float) $h['quantity'],
                            'total_cost'      => (float) $h['total_cost'],
                            'current_price'   => $h['current_price'] !== null ? (float) $h['current_price'] : null,
                            'current_value'   => $h['current_value'] !== null ? (float) $h['current_value'] : null,
                            'unrealized_gain' => $h['unrealized_gain'] !== null ? (float) $h['unrealized_gain'] : null,
                            'pct'             => (float) $h['pct'],
                            'sort_value'      => (float) $h['effective_value'],
                            'reclassify_url'  => route('assets.reclassify', $h['asset']),
                            'portfolios'      => $h['portfolios'],
                        ])->values()->all();
                    @endphp
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg"
                         x-data="holdingsSort({{ json_encode($holdingsRows) }})">
                        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">All Holdings</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th @click="sort('symbol')" class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 select-none">
                                            Symbol<span x-text="arrow('symbol')"></span>
                                        </th>
                                        <th @click="sort('asset_type')" class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 select-none">
                                            Type<span x-text="arrow('asset_type')"></span>
                                        </th>
                                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase select-none">
                                            Feed
                                        </th>
                                        <th @click="sort('quantity')" class="px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 select-none">
                                            Total Qty<span x-text="arrow('quantity')"></span>
                                        </th>
                                        <th @click="sort('total_cost')" class="px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 select-none">
                                            Cost Basis<span x-text="arrow('total_cost')"></span>
                                        </th>
                                        <th @click="sort('current_price')" class="px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 select-none">
                                            Price<span x-text="arrow('current_price')"></span>
                                        </th>
                                        <th @click="sort('sort_value')" class="px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 select-none">
                                            Market Value<span x-text="arrow('sort_value')"></span>
                                        </th>
                                        <th @click="sort('unrealized_gain')" class="px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 select-none">
                                            Unrealized P&L<span x-text="arrow('unrealized_gain')"></span>
                                        </th>
                                        <th @click="sort('pct')" class="px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase cursor-pointer hover:text-gray-700 dark:hover:text-gray-200 select-none">
                                            % of Total<span x-text="arrow('pct')"></span>
                                        </th>
                                    </tr>
                                </thead>
                                <template x-for="h in sorted" :key="h.asset_id">
                                    <tbody class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                            <td class="px-5 py-3">
                                                <button @click="openSymbol = openSymbol === h.symbol ? null : h.symbol"
                                                        class="font-mono font-semibold text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer leading-none"
                                                        x-text="h.symbol"></button>
                                            </td>
                                            <td class="px-5 py-3">
                                                <form :action="h.reclassify_url" method="POST" class="inline">
                                                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                                    <input type="hidden" name="_method" value="PATCH">
                                                    <select name="asset_type"
                                                            @change="$el.form.submit()"
                                                            title="Reclassify"
                                                            class="text-xs font-medium border-0 rounded px-2 py-0.5 cursor-pointer focus:ring-1 focus:ring-indigo-500"
                                                            :class="h.asset_type === 'crypto'
                                                                ? 'bg-orange-100 dark:bg-orange-900/60 text-orange-800 dark:text-orange-200'
                                                                : (h.asset_type === 'real_estate'
                                                                    ? 'bg-emerald-100 dark:bg-emerald-900/60 text-emerald-800 dark:text-emerald-200'
                                                                    : (h.asset_type === 'bond'
                                                                        ? 'bg-yellow-100 dark:bg-yellow-900/60 text-yellow-900 dark:text-yellow-200'
                                                                        : 'bg-blue-100 dark:bg-blue-900/60 text-blue-800 dark:text-blue-200'))">
                                                        <option value="stock"       :selected="h.asset_type === 'stock'">Stock</option>
                                                        <option value="crypto"      :selected="h.asset_type === 'crypto'">Crypto</option>
                                                        <option value="real_estate" :selected="h.asset_type === 'real_estate'">Real Estate</option>
                                                        <option value="bond"        :selected="h.asset_type === 'bond'">Bond</option>
                                                    </select>
                                                </form>
                                            </td>
                                            <td class="px-5 py-3">
                                                <form :action="h.reclassify_url" method="POST" class="inline">
                                                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                                    <input type="hidden" name="_method" value="PATCH">
                                                    <select name="price_source"
                                                            @change="$el.form.submit()"
                                                            title="Override price feed"
                                                            class="text-xs border-0 rounded px-2 py-0.5 cursor-pointer focus:ring-1 focus:ring-indigo-500 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                                        <option value=""          :selected="!h.price_source">Auto</option>
                                                        <option value="finnhub"   :selected="h.price_source === 'finnhub'">Finnhub</option>
                                                        <option value="coingecko" :selected="h.price_source === 'coingecko'">CoinGecko</option>
                                                    </select>
                                                </form>
                                            </td>
                                            <td class="px-5 py-3 text-right font-mono text-gray-900 dark:text-gray-100" x-text="fmtQty(h.quantity)"></td>
                                            <td class="px-5 py-3 text-right font-mono text-gray-700 dark:text-gray-300" x-text="fmtMoney(h.total_cost)"></td>
                                            <td class="px-5 py-3 text-right font-mono text-gray-500 dark:text-gray-400" x-text="fmtPrice(h.current_price)"></td>
                                            <td class="px-5 py-3 text-right font-mono font-semibold text-gray-900 dark:text-gray-100" x-text="fmtMoney(h.current_value)"></td>
                                            <td class="px-5 py-3 text-right font-mono font-semibold" :class="plClass(h.unrealized_gain)" x-text="plFmt(h.unrealized_gain)"></td>
                                            <td class="px-5 py-3 text-right font-mono text-gray-500 dark:text-gray-400" x-text="fmtPct(h.pct)"></td>
                                        </tr>
                                        <tr x-show="openSymbol === h.symbol" style="display:none">
                                            <td colspan="9" class="px-5 pb-4 bg-indigo-50/60 dark:bg-indigo-950/20">
                                                <div class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-2 pt-2">Held in</div>
                                                <div class="flex flex-wrap gap-3">
                                                    <template x-for="p in h.portfolios" :key="p.id">
                                                        <div class="min-w-[150px] rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 shadow-sm">
                                                            <div class="font-semibold text-gray-900 dark:text-gray-100 text-sm" x-text="p.name"></div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5" x-text="fmtQty(p.qty) + ' shares'"></div>
                                                            <div class="text-xs font-mono font-semibold text-gray-700 dark:text-gray-300 mt-0.5" x-text="fmtMoney(p.value)"></div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </template>
                            </table>
                        </div>
                    </div>
                @endif

                @if ($hasPortfolios)
                {{-- Per-portfolio breakdown --}}
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Portfolios</h3>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($summaries as $s)
                            <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <div>
                                    <a href="{{ route('portfolios.show', $s['portfolio']) }}"
                                       class="font-medium text-gray-900 dark:text-gray-100 hover:underline">
                                        {{ $s['portfolio']->name }}
                                    </a>
                                    @if ($s['portfolio']->description)
                                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $s['portfolio']->description }}</p>
                                    @endif
                                </div>
                                <div class="flex items-center gap-4 sm:gap-8 text-right text-sm">
                                    <div class="hidden sm:block">
                                        <p class="text-xs text-gray-400 dark:text-gray-500">Cost Basis</p>
                                        <p class="font-mono text-gray-700 dark:text-gray-300">${{ number_format($s['cost_basis'], 2) }}</p>
                                    </div>
                                    @if ($s['market_value'] !== null)
                                        <div>
                                            <p class="text-xs text-gray-400 dark:text-gray-500">Market Value</p>
                                            <p class="font-mono text-gray-900 dark:text-gray-100 font-semibold">${{ number_format($s['market_value'], 2) }}</p>
                                        </div>
                                        <div class="hidden sm:block">
                                            <p class="text-xs text-gray-400 dark:text-gray-500">P&L</p>
                                            @php $unr = $s['unrealized'] ?? 0; @endphp
                                            <p class="font-mono font-semibold {{ $unr >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                {{ $unr >= 0 ? '+' : '' }}${{ number_format($unr, 2) }}
                                            </p>
                                        </div>
                                    @endif
                                    @if ($s['manual_value'] > 0)
                                        <div class="hidden sm:block">
                                            <p class="text-xs text-gray-400 dark:text-gray-500">Manual</p>
                                            <p class="font-mono text-gray-700 dark:text-gray-300">${{ number_format($s['manual_value'], 2) }}</p>
                                        </div>
                                    @endif
                                    <a href="{{ route('portfolios.show', $s['portfolio']) }}"
                                       class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition shrink-0">
                                        View
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif

            @endif
        </div>
    </div>

    @if ($chartData->count() > 1 || $allocation['total'] > 0)
        <script>window.__dashCharts = { chartData: @json($chartData), chartDataExManual: @json($chartDataExManual), benchmarkData: @json($benchmarkData), allocation: @json($allocation) };</script>
        @vite('resources/js/dashboard-charts.js')
    @endif
</x-app-layout>

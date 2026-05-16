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

            @php
                $hasPortfolios = ! $summaries->isEmpty();
                $hasMoneyData  = $totals['total_value'] > 0 || $totals['total_debt'] > 0;
                $showNetWorth  = $totals['total_debt'] > 0 || (! $hasPortfolios && $totals['total_value'] > 0);
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
                            Add a cash account
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

                @if ($showNetWorth)
                    {{-- Net worth row --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Assets</p>
                            <p class="mt-1 text-2xl font-semibold font-mono text-gray-900 dark:text-gray-100">
                                ${{ number_format($totals['total_value'], 2) }}
                            </p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                <a href="{{ route('liabilities.index') }}" class="hover:underline">Total Debt</a>
                            </p>
                            <p class="mt-1 text-2xl font-semibold font-mono text-red-600 dark:text-red-400">
                                −${{ number_format($totals['total_debt'], 2) }}
                            </p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4 ring-1 ring-indigo-500/20">
                            <p class="text-xs text-indigo-600 dark:text-indigo-400 uppercase tracking-wide font-semibold">Net Worth</p>
                            <p class="mt-1 text-2xl font-semibold font-mono {{ $totals['net_worth'] >= 0 ? 'text-gray-900 dark:text-gray-100' : 'text-red-600' }}">
                                {{ $totals['net_worth'] < 0 ? '−' : '' }}${{ number_format(abs($totals['net_worth']), 2) }}
                            </p>
                        </div>
                    </div>
                @endif

                @if ($hasPortfolios)
                    {{-- Portfolio totals --}}
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
                                <p id="tile-market-value" class="mt-1 text-2xl font-semibold font-mono text-gray-900 dark:text-gray-100">
                                    ${{ number_format($totals['market_value'], 2) }}
                                </p>
                            </div>

                            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                                <p id="tile-pl-label" class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Unrealized P&L</p>
                                @php $unr = $totals['unrealized'] ?? 0; @endphp
                                <p id="tile-pl-value" class="mt-1 text-2xl font-semibold font-mono {{ $unr >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $unr >= 0 ? '+' : '' }}${{ number_format($unr, 2) }}
                                </p>
                            </div>
                        @endif

                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Assets</p>
                            <p id="tile-total-value" class="mt-1 text-2xl font-semibold font-mono text-gray-900 dark:text-gray-100">
                                ${{ number_format($totals['total_value'], 2) }}
                            </p>
                        </div>
                    </div>
                @endif

                @if ($chartData->isNotEmpty())
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
                            <div class="w-64 h-64 shrink-0 relative"><canvas id="allocationDonut"></canvas></div>
                            <div class="space-y-2 text-sm">
                                @foreach ($allocation['labels'] as $i => $label)
                                    @php $val = $allocation['values'][$i]; @endphp
                                    @if ($val > 0)
                                        <div class="flex items-center gap-3">
                                            <span class="w-3 h-3 rounded-full shrink-0" style="background:{{ $allocation['colors'][$i] }}"></span>
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
                    @php
                        $holdingsRows = $allHoldings->map(fn ($h) => [
                            'symbol'          => $h['asset']->symbol,
                            'asset_type'      => $h['asset']->asset_type,
                            'asset_id'        => $h['asset']->id,
                            'quantity'        => (float) $h['quantity'],
                            'total_cost'      => (float) $h['total_cost'],
                            'current_price'   => $h['current_price'] !== null ? (float) $h['current_price'] : null,
                            'current_value'   => $h['current_value'] !== null ? (float) $h['current_value'] : null,
                            'unrealized_gain' => $h['unrealized_gain'] !== null ? (float) $h['unrealized_gain'] : null,
                            'pct'             => (float) $h['pct'],
                            'sort_value'      => (float) $h['effective_value'],
                            'reclassify_url'  => route('assets.reclassify', $h['asset']),
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
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                                    <template x-for="h in sorted" :key="h.asset_id">
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                            <td class="px-5 py-3 font-mono font-semibold text-gray-900 dark:text-gray-100" x-text="h.symbol"></td>
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
                                            <td class="px-5 py-3 text-right font-mono text-gray-900 dark:text-gray-100" x-text="fmtQty(h.quantity)"></td>
                                            <td class="px-5 py-3 text-right font-mono text-gray-700 dark:text-gray-300" x-text="fmtMoney(h.total_cost)"></td>
                                            <td class="px-5 py-3 text-right font-mono text-gray-500 dark:text-gray-400" x-text="fmtPrice(h.current_price)"></td>
                                            <td class="px-5 py-3 text-right font-mono font-semibold text-gray-900 dark:text-gray-100" x-text="fmtMoney(h.current_value)"></td>
                                            <td class="px-5 py-3 text-right font-mono font-semibold" :class="plClass(h.unrealized_gain)" x-text="plFmt(h.unrealized_gain)"></td>
                                            <td class="px-5 py-3 text-right font-mono text-gray-500 dark:text-gray-400" x-text="fmtPct(h.pct)"></td>
                                        </tr>
                                    </template>
                                </tbody>
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

    @if ($chartData->isNotEmpty() || $allocation['total'] > 0)
        @vite('resources/js/chartjs.js')
        @push('scripts')
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const isDark      = document.documentElement.classList.contains('dark');
            const gridColor   = isDark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.07)';
            const labelColor  = isDark ? '#9ca3af' : '#6b7280';
            const allDataFull = @json($chartData);          // includes manual assets
            const allDataMkt  = @json($chartDataExManual);  // market-only (no manual)
            const benchRaw    = @json($benchmarkData);
            const allocData   = @json($allocation);

            // Initial PHP tile values for restoring on "All" range
            const initMarketValue = {{ $totals['market_value'] ?? 'null' }};
            const initUnrealized  = {{ $totals['unrealized'] ?? 'null' }};
            const initTotal       = {{ $totals['total_value'] }};

            // Manual assets toggle (persisted in localStorage)
            let showManual = localStorage.getItem('dashShowManual') !== 'false';
            let allData    = showManual ? allDataFull : allDataMkt;

            const manualBtn = document.getElementById('manual-toggle');
            function syncManualToggle() {
                if (!manualBtn) return;
                if (showManual) {
                    manualBtn.classList.add('bg-indigo-100', 'dark:bg-indigo-900/40', 'text-indigo-700', 'dark:text-indigo-300', 'border-indigo-300', 'dark:border-indigo-600');
                    manualBtn.classList.remove('bg-gray-100', 'dark:bg-gray-700', 'text-gray-500', 'dark:text-gray-400', 'border-gray-300', 'dark:border-gray-600');
                } else {
                    manualBtn.classList.remove('bg-indigo-100', 'dark:bg-indigo-900/40', 'text-indigo-700', 'dark:text-indigo-300', 'border-indigo-300', 'dark:border-indigo-600');
                    manualBtn.classList.add('bg-gray-100', 'dark:bg-gray-700', 'text-gray-500', 'dark:text-gray-400', 'border-gray-300', 'dark:border-gray-600');
                }
            }
            syncManualToggle();
            if (manualBtn) {
                manualBtn.addEventListener('click', () => {
                    showManual = !showManual;
                    allData = showManual ? allDataFull : allDataMkt;
                    localStorage.setItem('dashShowManual', showManual);
                    syncManualToggle();
                    updateDashChart(dashRange);
                });
            }

            // ── Helpers ──────────────────────────────────────────────
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

            function activateBtn(containerSelector, activeRange) {
                document.querySelectorAll(containerSelector + ' button[data-range]').forEach(b => {
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

            // ── Tile updates ─────────────────────────────────────────
            function updateTiles(filtered, range) {
                if (!filtered.length) return;
                const last  = filtered[filtered.length - 1];
                const first = filtered[0];

                // Market Value tile
                const mvEl = document.getElementById('tile-market-value');
                if (mvEl) mvEl.textContent = fmtFull(last.value);

                // Total Assets tile
                const totEl = document.getElementById('tile-total-value');
                if (totEl) totEl.textContent = fmtFull(last.value);

                // P&L tile — period gain vs start of range; "All" uses PHP-computed unrealized
                const plEl    = document.getElementById('tile-pl-value');
                const plLabel = document.getElementById('tile-pl-label');
                if (plEl) {
                    let pl, label;
                    if (range === 'All' && initUnrealized !== null) {
                        pl    = initUnrealized;
                        label = 'Unrealized P&L';
                    } else {
                        pl    = last.value - first.value;
                        label = range + ' Gain/Loss';
                    }
                    const sign = pl >= 0 ? '+$' : '-$';
                    plEl.textContent = sign + Math.abs(pl).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    plEl.className   = 'mt-1 text-2xl font-semibold font-mono ' + (pl >= 0 ? 'text-green-600' : 'text-red-600');
                    if (plLabel) plLabel.textContent = label;
                }
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
            const dashChartEl = document.getElementById('dashChart');
            if (dashChartEl) {
                let dashRange = '1Y';
                const dashChart = new Chart(dashChartEl, {
                    type: 'line',
                    data: {
                        datasets: [
                            { label: 'Portfolio Value', data: [], borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.15)', fill: true, tension: 0.3, borderWidth: 2, pointRadius: 0 },
                            { label: 'Cost Basis',      data: [], borderColor: '#94a3b8', borderDash: [5, 5], fill: false, tension: 0.3, borderWidth: 2, pointRadius: 0 },
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

                function updateDashChart(range) {
                    const filtered = filterByRange(allData, range);
                    dashChart.data.datasets[0].data = filtered.map(r => pointFromRow(r, 'value'));
                    dashChart.data.datasets[1].data = filtered.map(r => pointFromRow(r, 'cost'));
                    dashChart.update();
                    activateBtn('#dash-range-btns', range);
                    updateTiles(filtered, range);
                }

                document.querySelectorAll('#dash-range-btns button[data-range]').forEach(b =>
                    b.addEventListener('click', () => { dashRange = b.dataset.range; updateDashChart(dashRange); })
                );
                updateDashChart(dashRange);
            }

            // ── Benchmark Chart ───────────────────────────────────────
            const benchEl = document.getElementById('benchmarkChart');
            if (benchEl && Object.keys(benchRaw).length > 0) {
                let benchRange = '1Y';
                const benchColors = { 'My Portfolio': '#6366f1', SPY: '#10b981', BTC: '#f59e0b' };

                function normalize(data) {
                    if (!data || data.length === 0) return [];
                    const base = data[0].price;
                    if (!base) return [];
                    return data.map(r => ({ x: new Date(r.date).getTime(), y: parseFloat(((r.price / base - 1) * 100).toFixed(2)) }));
                }

                function buildPortfolioNorm(range) {
                    const cut      = cutoffDate(range);
                    const filtered = cut ? allData.filter(r => new Date(r.date) >= cut) : allData;
                    if (!filtered.length) return [];
                    const base = filtered[0].value;
                    return filtered.map(r => ({ x: new Date(r.date).getTime(), y: parseFloat(((r.value / base - 1) * 100).toFixed(2)) }));
                }

                function buildBenchNorm(ticker, range) {
                    const raw      = benchRaw[ticker] || [];
                    const cut      = cutoffDate(range);
                    const filtered = cut ? raw.filter(r => new Date(r.date) >= cut) : raw;
                    return normalize(filtered);
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
                    const datasets = [{ label: 'My Portfolio', data: buildPortfolioNorm(range), borderColor: benchColors['My Portfolio'], fill: false, tension: 0.3, borderWidth: 2, pointRadius: 0 }];
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
                    activateBtn('#bench-range-btns', range);
                }

                document.querySelectorAll('#bench-range-btns button[data-range]').forEach(b =>
                    b.addEventListener('click', () => { benchRange = b.dataset.range; updateBenchChart(benchRange); })
                );
                updateBenchChart(benchRange);
            }

            // ── Allocation Donut ──────────────────────────────────────
            const donutEl = document.getElementById('allocationDonut');
            if (donutEl && allocData.total > 0) {
                new Chart(donutEl, {
                    type: 'pie',
                    data: {
                        labels: allocData.labels,
                        datasets: [{ data: allocData.values, backgroundColor: allocData.colors, borderWidth: 0 }],
                    },
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

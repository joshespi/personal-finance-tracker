<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">Money</p>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Debt Payoff Planner</h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @if (! $data['has_data'])
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-sm text-gray-600 dark:text-gray-400">
                    <p class="font-semibold text-gray-800 dark:text-gray-200 mb-2">No active debt found.</p>
                    <p>Add liabilities with a current balance to see your payoff plan.</p>
                    <a href="{{ route('liabilities.index') }}" class="inline-block mt-3 text-indigo-600 dark:text-indigo-400 hover:underline">
                        Go to Liabilities &rarr;
                    </a>
                </div>
            @else
                @php
                    $debts     = $data['debts'];
                    $mortgages = $data['mortgages'];
                    $snowball  = $data['snowball'];
                    $avalanche = $data['avalanche'];
                @endphp

                @if ($data['negative_amortization_count'] > 0)
                    <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-600 rounded-lg px-5 py-4 text-sm text-amber-800 dark:text-amber-200">
                        <strong>Warning:</strong> {{ $data['negative_amortization_count'] }} {{ Str::plural('debt', $data['negative_amortization_count']) }}
                        have a minimum payment lower than the monthly interest — the balance is growing. Increase those payments to make progress.
                    </div>
                @endif

                @if (count(array_filter($debts, fn($d) => ! $d['min_payment_set'])) > 0)
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-300 dark:border-blue-600 rounded-lg px-5 py-4 text-sm text-blue-800 dark:text-blue-200">
                        Some debts don't have a minimum payment set — using a 2% estimate. Edit the liability to enter the exact amount for a more accurate plan.
                    </div>
                @endif

                {{-- Seed PHP data into JS vars so @json inside x-data="" doesn't break the HTML attribute --}}
                <script>
                    var __debtPayoffInputs   = @json(array_values($debts));
                    var __debtPayoffSnowball = @json($snowball);
                    var __debtPayoffAvalanche= @json($avalanche);
                </script>

                {{-- Alpine component wraps the interactive section --}}
                <div
                    x-data="{
                        debtInputs: __debtPayoffInputs,
                        extraPayment: 0,
                        strategy: 'snowball',
                        snowballResult: __debtPayoffSnowball,
                        avalancheResult: __debtPayoffAvalanche,
                        chart: null,
                        _debounce: null,

                        get activeResult() {
                            return this.strategy === 'snowball' ? this.snowballResult : this.avalancheResult;
                        },

                        formatMonths(n) {
                            if (!n || n >= 600) return '50+ yrs';
                            const y = Math.floor(n / 12);
                            const m = n % 12;
                            if (y === 0) return m + ' mo';
                            if (m === 0) return y + ' yr';
                            return y + 'yr ' + m + 'mo';
                        },

                        payoffDate(months) {
                            if (!months || months >= 600) return '50+ yrs';
                            const d = new Date();
                            d.setMonth(d.getMonth() + months);
                            return d.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                        },

                        recalculate() {
                            const extra = parseFloat(this.extraPayment) || 0;
                            const totalBudget = extra + this.debtInputs.reduce((s, d) => s + d.min_payment, 0);
                            const snowballIds  = [...this.debtInputs].sort((a, b) => a.balance - b.balance).map(d => d.id);
                            const avalancheIds = [...this.debtInputs].sort((a, b) => b.apr - a.apr).map(d => d.id);
                            this.snowballResult  = this.runSimulation(snowballIds, totalBudget);
                            this.avalancheResult = this.runSimulation(avalancheIds, totalBudget);
                            this.updateChart();
                        },

                        runSimulation(priorityIds, totalBudget) {
                            const bal  = {};
                            const byId = {};
                            this.debtInputs.forEach(d => { bal[d.id] = d.balance; byId[d.id] = d; });
                            let totalInterest = 0;
                            const payoff   = {};
                            const timeline = [];
                            const MAX = 600;
                            for (let month = 1; month <= MAX; month++) {
                                this.debtInputs.forEach(d => {
                                    if (bal[d.id] <= 0) return;
                                    const i = bal[d.id] * d.monthly_rate;
                                    bal[d.id] += i;
                                    totalInterest += i;
                                });
                                let priorityId = null;
                                for (const pid of priorityIds) {
                                    if (bal[pid] > 0) { priorityId = pid; break; }
                                }
                                let allocated = 0;
                                this.debtInputs.forEach(d => {
                                    if (d.id === priorityId || bal[d.id] <= 0) return;
                                    const pmt = Math.min(d.min_payment, bal[d.id]);
                                    bal[d.id] -= pmt;
                                    allocated += pmt;
                                    if (bal[d.id] <= 0.01) {
                                        bal[d.id] = 0;
                                        if (!payoff[d.id]) payoff[d.id] = month;
                                    }
                                });
                                if (priorityId && bal[priorityId] > 0) {
                                    const pmt = Math.min(Math.max(0, totalBudget - allocated), bal[priorityId]);
                                    bal[priorityId] -= pmt;
                                    if (bal[priorityId] <= 0.01) {
                                        bal[priorityId] = 0;
                                        if (!payoff[priorityId]) payoff[priorityId] = month;
                                    }
                                }
                                const total = Math.max(0, Object.values(bal).reduce((s, v) => s + v, 0));
                                timeline.push(Math.round(total * 100) / 100);
                                if (total <= 0.01) break;
                            }
                            const keys = Object.values(payoff);
                            const months = keys.length > 0 ? Math.max(...keys) : MAX;
                            return { months, total_interest: Math.round(totalInterest * 100) / 100, payoff_per_debt: payoff, timeline };
                        },

                        chartLabels(len) {
                            return Array.from({ length: len }, (_, i) => {
                                const d = new Date();
                                d.setMonth(d.getMonth() + i + 1);
                                return d.toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
                            });
                        },

                        updateChart() {
                            if (!this.chart) return;
                            const s = this.snowballResult.timeline;
                            const a = this.avalancheResult.timeline;
                            const maxLen = Math.max(s.length, a.length);
                            this.chart.data.labels = this.chartLabels(maxLen);
                            this.chart.data.datasets[0].data = s;
                            this.chart.data.datasets[1].data = a;
                            this.chart.update('none');
                        },

                        init() {
                            this.$nextTick(() => {
                                const ctx = document.getElementById('debtChart');
                                if (!ctx) return;
                                const isDark = document.documentElement.classList.contains('dark');
                                const tickColor = isDark ? '#9ca3af' : '#6b7280';
                                const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
                                const sData = __debtPayoffSnowball.timeline;
                                const aData = __debtPayoffAvalanche.timeline;
                                const maxLen = Math.max(sData.length, aData.length);
                                this.chart = new Chart(ctx, {
                                    type: 'line',
                                    data: {
                                        labels: this.chartLabels(maxLen),
                                        datasets: [
                                            {
                                                label: 'Snowball',
                                                data: sData,
                                                borderColor: '#6366f1',
                                                backgroundColor: 'rgba(99,102,241,0.08)',
                                                fill: true,
                                                tension: 0.3,
                                                pointRadius: 0,
                                                borderWidth: 2,
                                            },
                                            {
                                                label: 'Avalanche',
                                                data: aData,
                                                borderColor: '#10b981',
                                                backgroundColor: 'rgba(16,185,129,0.04)',
                                                fill: false,
                                                tension: 0.3,
                                                pointRadius: 0,
                                                borderWidth: 2,
                                                borderDash: [5, 4],
                                            },
                                        ],
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        animation: false,
                                        plugins: {
                                            legend: {
                                                position: 'top',
                                                labels: { color: tickColor, usePointStyle: true, pointStyleWidth: 10 },
                                            },
                                            tooltip: {
                                                callbacks: {
                                                    label: ctx => ctx.dataset.label + ': $' + Math.round(ctx.raw).toLocaleString(),
                                                },
                                            },
                                        },
                                        scales: {
                                            x: {
                                                ticks: { maxTicksLimit: 12, color: tickColor },
                                                grid:  { color: gridColor },
                                            },
                                            y: {
                                                ticks: { callback: v => '$' + Math.round(v).toLocaleString(), color: tickColor },
                                                grid:  { color: gridColor },
                                            },
                                        },
                                    },
                                });
                            });

                            this.$watch('extraPayment', () => {
                                clearTimeout(this._debounce);
                                this._debounce = setTimeout(() => this.recalculate(), 250);
                            });
                        },
                    }"
                    class="space-y-8"
                >
                    {{-- Hero tiles --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total debt</p>
                            <p class="mt-1 text-2xl font-semibold font-mono text-gray-800 dark:text-gray-100">
                                ${{ number_format($data['total_balance'], 0) }}
                            </p>
                            <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">high-interest only</p>
                        </div>

                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Interest / month</p>
                            <p class="mt-1 text-2xl font-semibold font-mono text-rose-600 dark:text-rose-400">
                                ${{ number_format($data['total_monthly_interest'], 0) }}
                            </p>
                            <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">given away to lenders</p>
                        </div>

                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Interest / year</p>
                            <p class="mt-1 text-2xl font-semibold font-mono text-rose-600 dark:text-rose-400">
                                ${{ number_format($data['yearly_interest'], 0) }}
                            </p>
                            <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">at current balances</p>
                        </div>

                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Payoff (min only)</p>
                            <p class="mt-1 text-2xl font-semibold font-mono text-gray-800 dark:text-gray-100">
                                @if ($snowball['months'] >= 600)
                                    50+ yrs
                                @else
                                    {{ floor($snowball['months'] / 12) > 0 ? floor($snowball['months'] / 12) . 'yr ' : '' }}{{ $snowball['months'] % 12 > 0 ? ($snowball['months'] % 12) . 'mo' : '' }}
                                @endif
                            </p>
                            <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                                @if ($snowball['months'] < 600)
                                    {{ \Carbon\Carbon::now()->addMonths($snowball['months'])->format('M Y') }}
                                @endif
                            </p>
                        </div>
                    </div>

                    {{-- Extra payment slider --}}
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">Extra monthly payment</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Slide to see how extra money accelerates your payoff</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-gray-500 dark:text-gray-400 text-sm">$</span>
                                <input type="number" min="0" max="10000" step="25"
                                       x-model="extraPayment"
                                       class="w-28 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm text-right font-mono" />
                            </div>
                        </div>
                        <input type="range" min="0" max="2000" step="25"
                               x-model="extraPayment"
                               class="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-lg appearance-none cursor-pointer accent-indigo-600" />
                        <div class="flex justify-between text-xs text-gray-400 dark:text-gray-500 mt-1">
                            <span>$0</span><span>$500</span><span>$1,000</span><span>$1,500</span><span>$2,000</span>
                        </div>
                    </div>

                    {{-- Strategy cards --}}
                    <div class="grid sm:grid-cols-2 gap-4">
                        {{-- Snowball --}}
                        <button type="button"
                                @click="strategy = 'snowball'"
                                :class="strategy === 'snowball'
                                    ? 'ring-2 ring-indigo-500'
                                    : 'opacity-80 hover:opacity-100'"
                                class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5 text-left transition">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="inline-block w-3 h-3 rounded-full bg-indigo-500"></span>
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">Snowball</p>
                                <span class="text-xs text-gray-400 dark:text-gray-500 ml-auto">lowest balance first</span>
                            </div>
                            <p class="text-2xl font-semibold font-mono text-indigo-600 dark:text-indigo-400" x-text="formatMonths(snowballResult.months)"></p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5" x-text="payoffDate(snowballResult.months)"></p>
                            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                                Total interest: <span class="font-mono font-semibold" x-text="'$' + Math.round(snowballResult.total_interest).toLocaleString()"></span>
                            </p>
                        </button>

                        {{-- Avalanche --}}
                        <button type="button"
                                @click="strategy = 'avalanche'"
                                :class="strategy === 'avalanche'
                                    ? 'ring-2 ring-emerald-500'
                                    : 'opacity-80 hover:opacity-100'"
                                class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5 text-left transition">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="inline-block w-3 h-3 rounded-full bg-emerald-500"></span>
                                <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">Avalanche</p>
                                <span class="text-xs text-gray-400 dark:text-gray-500 ml-auto">highest rate first</span>
                            </div>
                            <p class="text-2xl font-semibold font-mono text-emerald-600 dark:text-emerald-400" x-text="formatMonths(avalancheResult.months)"></p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5" x-text="payoffDate(avalancheResult.months)"></p>
                            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                                Total interest: <span class="font-mono font-semibold" x-text="'$' + Math.round(avalancheResult.total_interest).toLocaleString()"></span>
                            </p>
                        </button>
                    </div>

                    {{-- Avalanche savings callout (only shows when it matters) --}}
                    <template x-if="avalancheResult.total_interest < snowballResult.total_interest">
                        <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-300 dark:border-emerald-700 rounded-lg px-5 py-3 text-sm text-emerald-800 dark:text-emerald-200">
                            Avalanche saves you <strong x-text="'$' + Math.round(snowballResult.total_interest - avalancheResult.total_interest).toLocaleString()"></strong> in interest
                            and finishes <strong x-text="Math.abs(snowballResult.months - avalancheResult.months) + ' months'"></strong> faster.
                        </div>
                    </template>

                    {{-- Balance chart --}}
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">Remaining balance over time</p>
                        <div class="h-64">
                            <canvas id="debtChart"></canvas>
                        </div>
                    </div>

                    {{-- Per-debt table --}}
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">High-interest debts</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-900/40">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Debt</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Balance</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">APR</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Interest/mo</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Min pmt</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Snowball</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Avalanche</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach ($debts as $d)
                                        @php $debtId = $d['id']; @endphp
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                            <td class="px-4 py-3">
                                                <a href="{{ route('liabilities.show', $debtId) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                                    {{ $d['name'] }}
                                                </a>
                                                @if ($d['negative_amortization'])
                                                    <span class="ml-1 text-xs text-amber-600 dark:text-amber-400" title="Minimum payment is less than monthly interest">⚠</span>
                                                @endif
                                                @if (! $d['min_payment_set'])
                                                    <span class="ml-1 text-xs text-blue-500 dark:text-blue-400" title="Estimated minimum payment">est.</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-gray-300">${{ number_format($d['balance'], 0) }}</td>
                                            <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-gray-300">{{ $d['apr'] > 0 ? number_format($d['apr'], 2) . '%' : '—' }}</td>
                                            <td class="px-4 py-3 text-right font-mono {{ $d['negative_amortization'] ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400' }}">
                                                ${{ number_format($d['monthly_interest'], 0) }}
                                            </td>
                                            <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-gray-300">
                                                ${{ number_format($d['min_payment'], 0) }}
                                            </td>
                                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400" x-text="snowballResult.payoff_per_debt['{{ $debtId }}'] ? payoffDate(snowballResult.payoff_per_debt['{{ $debtId }}']) : '—'"></td>
                                            <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400" x-text="avalancheResult.payoff_per_debt['{{ $debtId }}'] ? payoffDate(avalancheResult.payoff_per_debt['{{ $debtId }}']) : '—'"></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>{{-- end Alpine component --}}

                {{-- Mortgage section (calm, outside Alpine) --}}
                @if (count($mortgages) > 0)
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">Long-term financing</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Mortgages are excluded from payoff strategies — they're managed separately.</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-900/40">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Loan</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Balance</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">APR</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Interest/mo</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Min pmt</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach ($mortgages as $m)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                            <td class="px-4 py-3">
                                                <a href="{{ route('liabilities.show', $m['id']) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                                    {{ $m['name'] }}
                                                </a>
                                            </td>
                                            <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-gray-300">${{ number_format($m['balance'], 0) }}</td>
                                            <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-gray-300">{{ $m['apr'] > 0 ? number_format($m['apr'], 2) . '%' : '—' }}</td>
                                            <td class="px-4 py-3 text-right font-mono text-gray-500 dark:text-gray-400">${{ number_format($m['monthly_interest'], 0) }}</td>
                                            <td class="px-4 py-3 text-right font-mono text-gray-500 dark:text-gray-400">
                                                {{ $m['min_payment'] !== null ? '$' . number_format($m['min_payment'], 0) : '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

            @endif
        </div>
    </div>
</x-app-layout>

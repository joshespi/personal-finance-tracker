<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Invest</p>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Pension</h2>
            </div>
            <a href="{{ route('pensions.create') }}"
               class="px-3 py-1.5 text-xs rounded-md font-medium bg-gray-800 dark:bg-gray-200 text-white dark:text-gray-900 hover:opacity-90 transition">
                + Add pension
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @if ($computed->isEmpty())
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-sm text-gray-600 dark:text-gray-400 space-y-2">
                    <p class="font-semibold text-gray-800 dark:text-gray-200">No pension tracked yet.</p>
                    <p>Track a defined-benefit pension (like a URS Tier&nbsp;1 pension) as three views: its present value in your net worth,
                       your accrual progress, and the retirement income it produces alongside your portfolio.</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Note: URS <em>Savings Plans</em> (401k, Roth) are ordinary investable balances —
                       add those as portfolio or manual assets, not here. This tracks the <strong>defined-benefit</strong> pension only.</p>
                    <a href="{{ route('pensions.create') }}" class="inline-block mt-2 text-indigo-600 dark:text-indigo-400 hover:underline">Add your pension &rarr;</a>
                </div>
            @else
                @php $primary = $computed->first(); @endphp

                {{-- ═══════════════ RETIREMENT INCOME FLOOR ═══════════════ --}}
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Retirement Income Floor</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            What you'd have coming in at age {{ $primary['retirementAge'] }}: your COLA'd pension plus a safe withdrawal from your portfolio.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-gray-100 dark:divide-gray-700">
                        <div class="px-6 py-5">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Pension (COLA'd)</p>
                            <p class="mt-1 text-2xl font-semibold font-mono text-indigo-600 dark:text-indigo-400">
                                ${{ $demo->amt($incomeFloor['pensionMonthly'], 0) }}<span class="text-sm text-gray-400 font-normal">/mo</span>
                            </p>
                            <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">${{ $demo->amt($incomeFloor['pensionAnnual'], 0) }}/yr for life</p>
                        </div>
                        <div class="px-6 py-5">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Portfolio @ {{ $incomeFloor['swr'] }}% SWR</p>
                            <p class="mt-1 text-2xl font-semibold font-mono text-emerald-600 dark:text-emerald-400">
                                ${{ $demo->amt($incomeFloor['portfolioMonthlyIncome'], 0) }}<span class="text-sm text-gray-400 font-normal">/mo</span>
                            </p>
                            <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                                on ${{ $demo->amt($incomeFloor['portfolioAtRetirement'], 0) }} projected
                            </p>
                        </div>
                        <div class="px-6 py-5 bg-gray-50 dark:bg-gray-900/40">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total income floor</p>
                            <p class="mt-1 text-2xl font-semibold font-mono text-gray-900 dark:text-gray-100">
                                ${{ $demo->amt($incomeFloor['totalMonthly'], 0) }}<span class="text-sm text-gray-400 font-normal">/mo</span>
                            </p>
                            <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">${{ $demo->amt($incomeFloor['totalAnnual'], 0) }}/yr</p>
                        </div>
                    </div>

                    @if ($incomeFloor['surplus'] !== null)
                        <div class="px-6 py-3 text-sm border-t border-gray-100 dark:border-gray-700
                                    {{ $incomeFloor['onTrack'] ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-800 dark:text-emerald-300' : 'bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-300' }}">
                            @if ($incomeFloor['onTrack'])
                                Covers your ${{ $demo->amt($incomeFloor['annualExpenses'], 0) }}/yr target with
                                <strong>${{ $demo->amt($incomeFloor['surplus'], 0) }}/yr to spare</strong> (${{ $demo->amt($incomeFloor['surplus'] / 12, 0) }}/mo).
                            @else
                                <strong>${{ $demo->amt(abs($incomeFloor['surplus']), 0) }}/yr short</strong> of your ${{ $demo->amt($incomeFloor['annualExpenses'], 0) }}/yr target
                                (${{ $demo->amt(abs($incomeFloor['surplus']) / 12, 0) }}/mo gap).
                            @endif
                        </div>
                    @endif

                    <div class="px-6 py-3 text-xs text-gray-400 dark:text-gray-500 border-t border-gray-100 dark:border-gray-700">
                        Portfolio base: ${{ $demo->amt($incomeFloor['portfolioBase'], 0) }} today (tracked portfolios + cash), grown {{ $incomeFloor['yearsToRetirement'] }} yrs at {{ $incomeFloor['expectedReturn'] }}%.
                        Add your URS 401(k)/Roth as assets to fold them in.
                        @if ($primary['currentAge'] === null)
                            <span class="text-amber-600 dark:text-amber-400">Add a birth year to the pension to project years-to-retirement.</span>
                        @endif
                    </div>
                </div>

                {{-- Sensitivity form --}}
                <form method="GET" action="{{ route('pensions.index') }}"
                      class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-5">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">Assumptions &mdash; slide these to test sensitivity</p>
                    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-x-4 gap-y-4">
                        @php
                            $sens = [
                                ['retirement_age',      'Draw age',        $primary['retirementAge'],   '1',    'yrs'],
                                ['expected_return',     'Portfolio return',$incomeFloor['expectedReturn'],'0.1', '%'],
                                ['swr',                 'Withdrawal rate', $incomeFloor['swr'],          '0.1',  '%'],
                                ['discount_rate',       'Discount rate',   $primary['discountRatePct'],  '0.1',  '%'],
                                ['life_expectancy',     'Life expectancy', $primary['lifeExpectancy'],   '1',    'age'],
                                ['monthly_contribution','Monthly savings', $incomeFloor['monthlyContribution'],'50','$'],
                            ];
                        @endphp
                        @foreach ($sens as [$field, $label, $val, $step, $suffix])
                            <div>
                                <label for="{{ $field }}" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">{{ $label }} <span class="text-gray-400">({{ $suffix }})</span></label>
                                <input id="{{ $field }}" name="{{ $field }}" type="number" step="{{ $step }}" min="0"
                                       value="{{ $val }}"
                                       class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" />
                            </div>
                        @endforeach
                        <div>
                            <label for="annual_expenses" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Target spend <span class="text-gray-400">($/yr)</span></label>
                            <input id="annual_expenses" name="annual_expenses" type="number" step="1000" min="0"
                                   value="{{ $incomeFloor['annualExpenses'] }}" placeholder="optional"
                                   class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" />
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-3">
                        <x-primary-button>Recalculate</x-primary-button>
                        <a href="{{ route('pensions.index') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:underline">Reset to stored</a>
                    </div>
                </form>

                {{-- ═══════════════ PER-PENSION: ACCRUAL + PRESENT VALUE ═══════════════ --}}
                @foreach ($computed as $row)
                    @php $p = $row['pension']; @endphp
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $demo->n($p->name) }}</h3>
                                @if ($p->plan_label)
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $demo->n($p->plan_label) }}</p>
                                @endif
                            </div>
                            <a href="{{ route('pensions.edit', $p) }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Edit</a>
                        </div>

                        {{-- Accrual ticker --}}
                        <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-y sm:divide-y-0 divide-gray-100 dark:divide-gray-700">
                            <div class="px-6 py-4">
                                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Service credit</p>
                                <p class="mt-1 text-xl font-semibold font-mono text-gray-800 dark:text-gray-100">{{ number_format($row['serviceNow'], 2) }}<span class="text-sm text-gray-400 font-normal"> yrs</span></p>
                            </div>
                            <div class="px-6 py-4">
                                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Accrued</p>
                                <p class="mt-1 text-xl font-semibold font-mono text-gray-800 dark:text-gray-100">{{ number_format($row['accruedPct'], 1) }}%</p>
                                <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">of final avg salary</p>
                            </div>
                            <div class="px-6 py-4">
                                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Earned benefit</p>
                                <p class="mt-1 text-xl font-semibold font-mono text-gray-800 dark:text-gray-100">${{ $demo->amt($row['accruedAnnual'], 0) }}<span class="text-sm text-gray-400 font-normal">/yr</span></p>
                                <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">${{ $demo->amt($row['accruedMonthly'], 0) }}/mo, today's $</p>
                            </div>
                            <div class="px-6 py-4">
                                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Each extra year</p>
                                <p class="mt-1 text-xl font-semibold font-mono text-emerald-600 dark:text-emerald-400">+${{ $demo->amt($row['marginalAnnualPerYear'], 0) }}<span class="text-sm text-gray-400 font-normal">/yr</span></p>
                                <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">≈ ${{ $demo->amt($row['marginalPvPerYear'], 0) }} in PV, for life</p>
                            </div>
                        </div>

                        {{-- Present value --}}
                        <div class="px-6 pt-5 pb-1 border-t border-gray-100 dark:border-gray-700">
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">What your pension is worth today</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-3xl leading-relaxed">
                                <strong>Present value</strong> is the lump sum you'd need invested today — earning a {{ $row['discountRatePct'] }}% real return —
                                to reproduce this pension's lifetime income. It's what your future pension is worth <em>now</em>, so you can weigh it against
                                your other assets. It counts toward net worth like home equity, but it isn't cash you can spend or rebalance.
                            </p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-gray-100 dark:divide-gray-700">
                            <div class="px-6 py-5">
                                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Worth today — earned so far</p>
                                <p class="mt-1 text-3xl font-semibold font-mono text-gray-900 dark:text-gray-100">${{ $demo->amt($row['pvAccrued'], 0) }}</p>
                                <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                                    ≈ today's value of the ${{ $demo->amt($row['accruedMonthly'], 0) }}/mo you've already earned, for life
                                    @if ($p->include_in_net_worth)
                                        · <span class="text-indigo-500 dark:text-indigo-400">counts in net worth</span>
                                    @else
                                        · <span class="text-gray-400">excluded from net worth</span>
                                    @endif
                                </p>
                            </div>
                            <div class="px-6 py-5">
                                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Worth today — if you work to {{ $row['retirementAge'] }}</p>
                                <p class="mt-1 text-3xl font-semibold font-mono text-gray-500 dark:text-gray-400">${{ $demo->amt($row['pvProjected'], 0) }}</p>
                                <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">≈ today's value of ${{ $demo->amt($row['projectedMonthly'], 0) }}/mo starting at {{ $row['retirementAge'] }} ({{ number_format($row['projectedPct'], 0) }}%, {{ number_format($row['serviceAtRetirement'], 1) }} yrs service)</p>
                            </div>
                        </div>

                        <div class="px-6 py-3 text-xs text-gray-400 dark:text-gray-500 border-t border-gray-100 dark:border-gray-700">
                            @if ($row['usesEstimate'])
                                Using your URS Benefit Estimate.
                            @endif
                            Valued as a COLA'd life annuity: {{ $row['payoutYears'] }} yrs of payments (draw {{ $row['retirementAge'] }} → age {{ $row['lifeExpectancy'] }}),
                            {{ $row['discountRatePct'] }}% real discount, {{ $row['annuityFactor'] }}× factor,
                            deferred {{ $row['yearsToDraw'] }} yrs.
                        </div>
                    </div>
                @endforeach

            @endif

        </div>
    </div>
</x-app-layout>

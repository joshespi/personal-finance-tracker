<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">Money</p>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">50/30/20 Budget Rule</h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @php
                $hasData = $data['has_data'];
                $ratios  = $data['ratios'];
                $targets = $data['targets'];
                $drift   = $data['drift'];
            @endphp

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-sm text-gray-600 dark:text-gray-400 space-y-2">
                <p class="text-gray-700 dark:text-gray-300">
                    The 50/30/20 rule splits monthly income into three buckets:
                    <strong>50% needs</strong> (rent, utilities, groceries, insurance — fixed costs you can't easily cut),
                    <strong>30% wants</strong> (dining, entertainment, subscriptions, and sinking funds for planned purchases like a vacation or gadget),
                    and <strong>20% wealth building</strong> (emergency fund until it's fully funded, then retirement contributions and investments).
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Figures below average the trailing {{ $data['window_months'] }} months
                    ({{ $data['window_start']->format('M Y') }} – {{ $data['window_end']->format('M Y') }}).
                    Mark envelopes as <em>mandatory</em> or <em>wealth building</em> in their edit form to classify them.
                </p>
            </div>

            {{-- Quick what-if calculator: type any income to see the 50/30/20 split. Pure client-side. --}}
            <div x-data="{
                    income: {{ ($data['monthly_income'] ?? 0) > 0 ? (int) round($data['monthly_income']) : 0 }},
                    fmt(n) { return '$' + Math.round(n || 0).toLocaleString('en-US'); }
                 }"
                 class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 space-y-5">
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Quick calculator</h3>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        Enter a monthly after-tax income to see the 50/30/20 split.
                        @if (($data['monthly_income'] ?? 0) > 0) Prefilled with your trailing average — edit it to run what-ifs. @endif
                    </p>
                </div>

                <div class="max-w-xs">
                    <label for="calc-income" class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Monthly after-tax income</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">$</span>
                        <input id="calc-income" type="number" min="0" step="50" inputmode="decimal" x-model.number="income"
                               class="w-full pl-7 pr-3 py-2 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-gray-900 dark:text-gray-100 font-mono focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="rounded-lg border border-gray-100 dark:border-gray-700 px-5 py-4">
                        <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <span class="inline-block w-2 h-2 rounded-full bg-indigo-500 mr-1"></span>Necessities <span class="text-gray-400 dark:text-gray-500">50%</span>
                        </p>
                        <p class="mt-1 text-2xl font-semibold font-mono text-gray-800 dark:text-gray-100" x-text="fmt(income * 0.5)"></p>
                        <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">Rent, utilities, groceries, insurance</p>
                    </div>
                    <div class="rounded-lg border border-gray-100 dark:border-gray-700 px-5 py-4">
                        <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <span class="inline-block w-2 h-2 rounded-full bg-sky-400 mr-1"></span>Wants <span class="text-gray-400 dark:text-gray-500">30%</span>
                        </p>
                        <p class="mt-1 text-2xl font-semibold font-mono text-gray-800 dark:text-gray-100" x-text="fmt(income * 0.3)"></p>
                        <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">Dining, entertainment, subscriptions</p>
                    </div>
                    <div class="rounded-lg border border-gray-100 dark:border-gray-700 px-5 py-4">
                        <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 mr-1"></span>Savings &amp; debt <span class="text-gray-400 dark:text-gray-500">20%</span>
                        </p>
                        <p class="mt-1 text-2xl font-semibold font-mono text-emerald-600 dark:text-emerald-400" x-text="fmt(income * 0.2)"></p>
                        <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">Emergency fund, investing, extra debt payoff</p>
                    </div>
                </div>
            </div>

            @if (! $hasData)
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-sm text-gray-600 dark:text-gray-400 space-y-2">
                    <p class="font-semibold text-gray-800 dark:text-gray-200">No income recorded in the last 6 months.</p>
                    <p>Add income entries so the calculator can compute your allocation ratios.</p>
                    <a href="{{ route('ready-to-assign') }}" class="inline-block mt-2 text-indigo-600 dark:text-indigo-400 hover:underline">Go to Ready to Assign &rarr;</a>
                </div>
            @else

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Monthly income</p>
                        <p class="mt-1 text-2xl font-semibold font-mono text-gray-800 dark:text-gray-100">
                            ${{ number_format($data['monthly_income'], 2) }}
                        </p>
                        <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">avg / month</p>
                    </div>

                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Mandatory</p>
                        <p class="mt-1 text-2xl font-semibold font-mono {{ $drift['mandatory_over'] ? 'text-amber-600 dark:text-amber-400' : 'text-gray-800 dark:text-gray-100' }}">
                            ${{ number_format($data['monthly_mandatory'], 2) }}
                        </p>
                        <p class="mt-0.5 text-xs {{ $drift['mandatory_over'] ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400 dark:text-gray-500' }}">
                            {{ $ratios['mandatory'] }}% (target ≤ {{ $targets['mandatory'] }}%)
                        </p>
                    </div>

                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Discretionary</p>
                        <p class="mt-1 text-2xl font-semibold font-mono text-gray-800 dark:text-gray-100">
                            ${{ number_format($data['monthly_discretionary'], 2) }}
                        </p>
                        <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                            {{ $ratios['discretionary'] }}% (target ≤ {{ $targets['discretionary'] }}%)
                        </p>
                    </div>

                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Wealth Building</p>
                        <p class="mt-1 text-2xl font-semibold font-mono {{ $drift['savings_under'] ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400' }}">
                            ${{ number_format($data['monthly_savings'], 2) }}
                        </p>
                        <p class="mt-0.5 text-xs {{ $drift['savings_under'] ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400 dark:text-gray-500' }}">
                            {{ $ratios['savings'] }}% (target ≥ {{ $targets['savings'] }}%)
                        </p>
                    </div>
                </div>

                {{-- Allocation bar --}}
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5 space-y-3">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Your allocation</p>
                    <div class="flex w-full h-6 rounded-full overflow-hidden bg-gray-100 dark:bg-gray-700">
                        @php
                            $m = max(0, min(100, $ratios['mandatory'] ?? 0));
                            $d = max(0, min(100 - $m, $ratios['discretionary'] ?? 0));
                            $s = max(0, 100 - $m - $d);
                        @endphp
                        <div class="h-full bg-indigo-500" style="width: {{ $m }}%" title="Needs {{ $ratios['mandatory'] }}% — fixed costs like rent, utilities, groceries"></div>
                        <div class="h-full bg-sky-400"    style="width: {{ $d }}%" title="Wants {{ $ratios['discretionary'] }}% — spending and planned purchases"></div>
                        <div class="h-full bg-emerald-500" style="width: {{ $s }}%" title="Wealth Building {{ $ratios['savings'] }}% — emergency fund and investing"></div>
                    </div>
                    <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                        <span><span class="inline-block w-2 h-2 rounded-full bg-indigo-500 mr-1"></span>Needs</span>
                        <span><span class="inline-block w-2 h-2 rounded-full bg-sky-400 mr-1"></span>Wants</span>
                        <span><span class="inline-block w-2 h-2 rounded-full bg-emerald-500 mr-1"></span>Wealth Building</span>
                    </div>
                </div>

                <x-budget-rule-drift-banner :drift="$drift" :ratios="$ratios" detailed />

                {{-- Savings phase --}}
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Where the 20% should go</h3>
                        <span class="text-xs uppercase tracking-wide font-semibold {{ $data['phase'] === 'funded' ? 'text-emerald-600 dark:text-emerald-400' : 'text-indigo-600 dark:text-indigo-400' }}">
                            {{ $data['phase'] === 'funded' ? 'Emergency fund complete' : 'Building emergency fund' }}
                        </span>
                    </div>

                    @if ($data['emergency_envelope'])
                        @php
                            $pct = $data['emergency_target'] > 0
                                ? min(100, round($data['emergency_balance'] / $data['emergency_target'] * 100))
                                : 0;
                            $remaining = max(0, $data['emergency_target'] - $data['emergency_balance']);
                        @endphp
                        <div>
                            <div class="flex justify-between text-sm mb-1.5">
                                <a href="{{ route('envelopes.show', $data['emergency_envelope']) }}"
                                   class="font-medium text-gray-700 dark:text-gray-300 hover:underline">
                                    {{ $data['emergency_envelope']->name }} ({{ $data['target_months'] }}-month target)
                                </a>
                                <span class="font-mono text-gray-600 dark:text-gray-400">
                                    ${{ number_format($data['emergency_balance'], 2) }} / ${{ number_format($data['emergency_target'], 2) }}
                                    <span class="text-gray-400 dark:text-gray-500 ml-1">({{ $pct }}%)</span>
                                </span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2.5 overflow-hidden">
                                <div class="h-full rounded-full transition-all {{ $data['emergency_funded'] ? 'bg-emerald-500' : 'bg-indigo-500' }}"
                                     style="width: {{ $pct }}%"></div>
                            </div>
                            @if (! $data['emergency_funded'] && $remaining > 0)
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                    ${{ number_format($remaining, 2) }} to go — direct your 20% here first.
                                </p>
                            @endif
                        </div>
                    @else
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            No envelope is marked as your emergency fund.
                            <a href="{{ route('envelopes.index') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Tag one &rarr;</a>
                        </div>
                    @endif

                    @if ($data['phase'] === 'funded')
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            Emergency fund is funded.
                            @if ($data['other_savings']->isNotEmpty())
                                Redirect your 20% to:
                                <ul class="list-disc list-inside mt-1.5 space-y-0.5">
                                    @foreach ($data['other_savings'] as $env)
                                        <li>
                                            <a href="{{ route('envelopes.show', $env) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $env->name }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                Tag retirement or investing envelopes as <em>wealth building</em> to direct future contributions there.
                            @endif
                        </div>
                    @endif
                </div>

            @endif
        </div>
    </div>
</x-app-layout>

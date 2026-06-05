<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Monthly Cashflow</p>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $month->format('F Y') }}
                </h2>
            </div>
            <div class="flex items-center gap-1">
                <a href="{{ route('cashflow', ['month' => $prevMonth]) }}"
                   class="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400 transition"
                   title="Previous month">&larr;</a>
                @if (!$isCurrentMonth)
                    <a href="{{ route('cashflow', ['month' => $nextMonth]) }}"
                       class="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400 transition"
                       title="Next month">&rarr;</a>
                    <a href="{{ route('cashflow') }}"
                       class="ml-2 text-xs text-indigo-600 dark:text-indigo-400 hover:underline">This month</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-8">

            {{-- Summary tiles --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Income</p>
                    <p class="mt-1 text-2xl font-semibold font-mono text-green-600 dark:text-green-400">
                        ${{ number_format($income, 2) }}
                    </p>
                </div>
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Spent</p>
                    <p class="mt-1 text-2xl font-semibold font-mono text-red-600 dark:text-red-400">
                        ${{ number_format($totalSpent, 2) }}
                    </p>
                </div>
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Net</p>
                    <p class="mt-1 text-2xl font-semibold font-mono {{ $net >= 0 ? 'text-gray-900 dark:text-gray-100' : 'text-red-600 dark:text-red-400' }}">
                        {{ $net < 0 ? '−' : '' }}${{ number_format(abs($net), 2) }}
                    </p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Income</h3>
                    <a href="{{ route('cash-accounts.index') }}"
                       class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">+ Add deposit</a>
                </div>
                @if ($incomeRows->isEmpty())
                    <div class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">No deposits recorded for this month.</div>
                @else
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($incomeRows as $entry)
                            <div class="px-6 py-3 flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-800 dark:text-gray-200">{{ $entry->description ?: '—' }}</p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500">{{ $entry->occurred_at->format('M j') }}</p>
                                </div>
                                <span class="text-sm font-mono font-semibold text-green-600 dark:text-green-400">
                                    ${{ number_format($entry->amount, 2) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- 6-month history chart --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">6-Month History</h3>
                <canvas id="cashflowChart" height="110"></canvas>
            </div>

            {{-- Envelope spending breakdown --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Spending by Envelope</h3>
                </div>

                @if ($envelopeGroups->isEmpty())
                    <div class="p-6 text-sm text-gray-500 dark:text-gray-400">No envelope activity for this month.</div>
                @else
                    @foreach ($envelopeGroups as $category => $rows)
                        @php $groupSpent = $rows->sum('spent'); @endphp
                        <div class="px-6 py-2 flex items-center justify-between bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $category }}</span>
                            <span class="text-xs font-mono text-gray-500 dark:text-gray-400">${{ number_format($groupSpent, 2) }}</span>
                        </div>
                        <div class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($rows as $row)
                                @php
                                    $pct  = $row['target'] > 0 ? min(100, round($row['spent'] / $row['target'] * 100)) : null;
                                    $over = $row['target'] > 0 && $row['spent'] > $row['target'];
                                @endphp
                                <div class="px-6 py-4">
                                    <div class="flex items-center justify-between mb-1.5">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-block w-2.5 h-2.5 rounded-full shrink-0"
                                                  style="background-color: {{ $row['envelope']->color }}"></span>
                                            <a href="{{ route('envelopes.show', $row['envelope']) }}"
                                               class="text-sm font-medium text-gray-800 dark:text-gray-200 hover:underline">
                                                {{ $row['envelope']->name }}
                                            </a>
                                        </div>
                                        <div class="text-sm font-mono text-right shrink-0 ml-4">
                                            <span class="{{ $over ? 'text-red-600 dark:text-red-400' : 'text-gray-800 dark:text-gray-200' }} font-semibold">
                                                ${{ number_format($row['spent'], 2) }}
                                            </span>
                                            @if ($row['target'] > 0)
                                                <span class="text-gray-400 dark:text-gray-500"> / ${{ number_format($row['target'], 2) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    @if ($pct !== null)
                                        <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-1.5 overflow-hidden">
                                            <div class="h-full rounded-full transition-all"
                                                 style="width: {{ $pct }}%; background-color: {{ $over ? '#dc2626' : $row['envelope']->color }};"></div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                @endif
            </div>

        </div>
    </div>

    @push('scripts')
    <script>
    (function () {
        const history = @json($history);
        const isDark   = document.documentElement.classList.contains('dark');
        const grid     = isDark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.06)';
        const ticks    = isDark ? '#9ca3af' : '#6b7280';

        new Chart(document.getElementById('cashflowChart'), {
            type: 'bar',
            data: {
                labels: history.map(h => h.month),
                datasets: [
                    {
                        label: 'Income',
                        data: history.map(h => h.income),
                        backgroundColor: 'rgba(34,197,94,0.65)',
                        borderColor: 'rgba(34,197,94,1)',
                        borderWidth: 1,
                        borderRadius: 3,
                    },
                    {
                        label: 'Spent',
                        data: history.map(h => h.spent),
                        backgroundColor: 'rgba(239,68,68,0.65)',
                        borderColor: 'rgba(239,68,68,1)',
                        borderWidth: 1,
                        borderRadius: 3,
                    },
                ],
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { labels: { color: ticks } },
                    tooltip: {
                        callbacks: {
                            label: ctx => ' $' + ctx.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2 }),
                        },
                    },
                },
                scales: {
                    x: { ticks: { color: ticks }, grid: { color: grid } },
                    y: {
                        beginAtZero: true,
                        ticks: { color: ticks, callback: v => '$' + v.toLocaleString('en-US') },
                        grid: { color: grid },
                    },
                },
            },
        });
    })();
    </script>
    @endpush
</x-app-layout>

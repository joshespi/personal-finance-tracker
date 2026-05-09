<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Envelopes</p>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Spending Trends</h2>
            </div>
            <div class="flex items-center gap-2">
                @foreach ([3, 6, 12] as $n)
                    <a href="{{ route('spending-trends', ['months' => $n]) }}"
                       class="px-3 py-1.5 text-sm rounded-md font-medium transition
                              {{ $lookback === $n
                                  ? 'bg-indigo-600 text-white'
                                  : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 border border-gray-200 dark:border-gray-600' }}">
                        {{ $n }}M
                    </a>
                @endforeach
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @if ($datasets->isEmpty())
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-8 text-center text-gray-500 dark:text-gray-400 text-sm">
                    No envelope spending recorded in the last {{ $lookback }} months.
                </div>
            @else

                {{-- Stacked bar chart --}}
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5">
                    <canvas id="trendsChart" height="120"></canvas>
                </div>

                {{-- Table --}}
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-700">
                                <th class="px-5 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Envelope</th>
                                @foreach ($monthLabels as $label)
                                    <th class="px-3 py-3 text-right font-semibold text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $label }}</th>
                                @endforeach
                                <th class="px-5 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/60">
                            @foreach ($datasets as $ds)
                                @php $rowTotal = array_sum($ds['data']->toArray()); @endphp
                                <tr class="hover:bg-gray-50/60 dark:hover:bg-gray-700/30 transition">
                                    <td class="px-5 py-3 text-gray-800 dark:text-gray-200">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-block w-2.5 h-2.5 rounded-full shrink-0"
                                                  style="background-color: {{ $ds['color'] }}"></span>
                                            {{ $ds['label'] }}
                                        </div>
                                    </td>
                                    @foreach ($ds['data'] as $val)
                                        <td class="px-3 py-3 text-right font-mono text-gray-700 dark:text-gray-300">
                                            {{ $val > 0 ? '$'.number_format($val, 2) : '—' }}
                                        </td>
                                    @endforeach
                                    <td class="px-5 py-3 text-right font-mono font-semibold text-gray-800 dark:text-gray-200">
                                        ${{ number_format($rowTotal, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-200 dark:border-gray-600">
                                <td class="px-5 py-3 font-semibold text-gray-700 dark:text-gray-300">Total</td>
                                @foreach ($monthlyTotals as $total)
                                    <td class="px-3 py-3 text-right font-mono font-semibold text-gray-800 dark:text-gray-200">
                                        ${{ number_format($total, 2) }}
                                    </td>
                                @endforeach
                                <td class="px-5 py-3 text-right font-mono font-semibold text-gray-800 dark:text-gray-200">
                                    ${{ number_format($monthlyTotals->sum(), 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            @endif
        </div>
    </div>

    @if ($datasets->isNotEmpty())
    @push('scripts')
    <script>
    (function () {
        const labels   = @json($monthLabels);
        const datasets = @json($datasets);
        const isDark   = document.documentElement.classList.contains('dark');
        const grid     = isDark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.06)';
        const ticks    = isDark ? '#9ca3af' : '#6b7280';

        new Chart(document.getElementById('trendsChart'), {
            type: 'bar',
            data: {
                labels,
                datasets: datasets.map(ds => ({
                    label: ds.label,
                    data: ds.data,
                    backgroundColor: ds.color + 'cc',
                    borderColor: ds.color,
                    borderWidth: 1,
                    borderRadius: 2,
                    stack: 'spend',
                })),
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: ticks, boxWidth: 12, padding: 16 },
                    },
                    tooltip: {
                        callbacks: {
                            label: ctx => ' ' + ctx.dataset.label + ': $' +
                                ctx.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2 }),
                        },
                    },
                },
                scales: {
                    x: { stacked: true, ticks: { color: ticks }, grid: { color: grid } },
                    y: {
                        stacked: true,
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
    @endif
</x-app-layout>

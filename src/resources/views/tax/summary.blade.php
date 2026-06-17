<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Tax Summary</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Tax-advantaged accounts (401k, IRA, HSA) are excluded.</p>
            </div>
            <a href="{{ route('export.realized-gains') }}"
               class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                ↓ Export CSV
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if ($byYear->isEmpty())
                <div class="text-center py-20 text-gray-400 dark:text-gray-500 text-sm">
                    No realized gains or losses yet. Sell a position to see your tax summary here.
                </div>
            @else

                {{-- Year selector --}}
                <div class="flex flex-wrap gap-2">
                    @foreach ($byYear as $row)
                        <a href="{{ route('tax.summary', ['year' => $row['year']]) }}"
                           class="px-4 py-1.5 rounded-full text-sm font-medium transition
                               {{ $row['year'] === $selectedYear
                                   ? 'bg-indigo-600 text-white'
                                   : 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-indigo-400' }}">
                            {{ $row['year'] }}
                        </a>
                    @endforeach
                </div>

                {{-- Year tiles --}}
                @if ($yearDetail)
                    @php
                        $total = $yearDetail['total_gain'];
                        $short = $yearDetail['short_gain'];
                        $long  = $yearDetail['long_gain'];
                        $fmt   = fn($v) => ($v >= 0 ? '+$' : '-$') . number_format(abs($v), 2);
                        $cls   = fn($v) => $v >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400';
                    @endphp

                    <div class="grid sm:grid-cols-3 gap-4">
                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Total Realized</p>
                            <p class="text-2xl font-bold {{ $cls($total) }}">{{ $fmt($total) }}</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Short-term <span class="font-normal">(held &lt; 1 year)</span></p>
                            <p class="text-2xl font-bold {{ $cls($short) }}">{{ $fmt($short) }}</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Long-term <span class="font-normal">(held &ge; 1 year)</span></p>
                            <p class="text-2xl font-bold {{ $cls($long) }}">{{ $fmt($long) }}</p>
                        </div>
                    </div>

                    {{-- Short-term lots --}}
                    @foreach (['short' => 'Short-term', 'long' => 'Long-term'] as $key => $label)
                        @php $lots = $yearDetail["{$key}_lots"]; @endphp
                        @if ($lots->isNotEmpty())
                            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                                <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $label }} Lots</h3>
                                    <span class="text-xs {{ $cls($yearDetail["{$key}_gain"]) }} font-medium">
                                        {{ $fmt($yearDetail["{$key}_gain"]) }}
                                    </span>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="text-xs text-gray-400 dark:text-gray-500 border-b border-gray-100 dark:border-gray-700">
                                                <th class="text-left px-4 py-2 font-medium">Symbol</th>
                                                <th class="text-left px-4 py-2 font-medium">Portfolio</th>
                                                <th class="text-right px-4 py-2 font-medium">Qty</th>
                                                <th class="text-right px-4 py-2 font-medium">Buy Date</th>
                                                <th class="text-right px-4 py-2 font-medium">Sell Date</th>
                                                <th class="text-right px-4 py-2 font-medium">Days</th>
                                                <th class="text-right px-4 py-2 font-medium">Cost Basis</th>
                                                <th class="text-right px-4 py-2 font-medium">Proceeds</th>
                                                <th class="text-right px-4 py-2 font-medium">Gain / Loss</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                                            @foreach ($lots as $lot)
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                                    <td class="px-4 py-2.5 font-mono font-medium text-gray-800 dark:text-gray-100">
                                                        {{ $lot['asset']->symbol }}
                                                    </td>
                                                    <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400">
                                                        {{ $lot['portfolio']->name }}
                                                    </td>
                                                    <td class="px-4 py-2.5 text-right text-gray-600 dark:text-gray-300 font-mono">
                                                        {{ rtrim(rtrim(number_format($lot['quantity'], 8), '0'), '.') }}
                                                    </td>
                                                    <td class="px-4 py-2.5 text-right text-gray-500 dark:text-gray-400">
                                                        {{ $lot['buy_date']->format('M j, Y') }}
                                                    </td>
                                                    <td class="px-4 py-2.5 text-right text-gray-500 dark:text-gray-400">
                                                        {{ $lot['sell_date']->format('M j, Y') }}
                                                    </td>
                                                    <td class="px-4 py-2.5 text-right text-gray-500 dark:text-gray-400">
                                                        {{ $lot['holding_days'] }}
                                                    </td>
                                                    <td class="px-4 py-2.5 text-right text-gray-600 dark:text-gray-300 font-mono">
                                                        ${{ number_format($lot['cost_basis'], 2) }}
                                                    </td>
                                                    <td class="px-4 py-2.5 text-right text-gray-600 dark:text-gray-300 font-mono">
                                                        ${{ number_format($lot['proceeds'], 2) }}
                                                    </td>
                                                    <td class="px-4 py-2.5 text-right font-mono font-semibold {{ $lot['gain'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400' }}">
                                                        {{ $lot['gain'] >= 0 ? '+' : '' }}${{ number_format($lot['gain'], 2) }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    @endforeach

                    {{-- Multi-year overview --}}
                    @if ($byYear->count() > 1)
                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                            <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700">
                                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">All Years</h3>
                            </div>
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-xs text-gray-400 dark:text-gray-500 border-b border-gray-100 dark:border-gray-700">
                                        <th class="text-left px-4 py-2 font-medium">Year</th>
                                        <th class="text-right px-4 py-2 font-medium">Short-term</th>
                                        <th class="text-right px-4 py-2 font-medium">Long-term</th>
                                        <th class="text-right px-4 py-2 font-medium">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                                    @foreach ($byYear as $row)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 {{ $row['year'] === $selectedYear ? 'bg-indigo-50 dark:bg-indigo-900/20' : '' }}">
                                            <td class="px-4 py-2.5">
                                                <a href="{{ route('tax.summary', ['year' => $row['year']]) }}"
                                                   class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                                                    {{ $row['year'] }}
                                                </a>
                                            </td>
                                            <td class="px-4 py-2.5 text-right font-mono {{ $row['short_gain'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400' }}">
                                                {{ $fmt($row['short_gain']) }}
                                            </td>
                                            <td class="px-4 py-2.5 text-right font-mono {{ $row['long_gain'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400' }}">
                                                {{ $fmt($row['long_gain']) }}
                                            </td>
                                            <td class="px-4 py-2.5 text-right font-mono font-semibold {{ $row['total_gain'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400' }}">
                                                {{ $fmt($row['total_gain']) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                @endif
            @endif

        </div>
    </div>
</x-app-layout>

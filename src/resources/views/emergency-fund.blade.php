<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">Money</p>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Emergency Fund</h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @if ($mandatoryEnvelopes->isEmpty() || $monthlyBaseline == 0)
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-sm text-gray-600 dark:text-gray-400 space-y-2">
                    <p class="font-semibold text-gray-800 dark:text-gray-200">No mandatory expenses configured.</p>
                    <p>Mark envelopes as <strong>Mandatory expense</strong> in their edit form to build your target. Typical candidates: rent, utilities, groceries, insurance.</p>
                    <a href="{{ route('envelopes.index') }}" class="inline-block mt-2 text-indigo-600 dark:text-indigo-400 hover:underline">Go to envelopes &rarr;</a>
                </div>
            @else

                {{-- Summary tiles --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Monthly baseline</p>
                        <p class="mt-1 text-2xl font-semibold font-mono text-gray-800 dark:text-gray-100">
                            ${{ number_format($monthlyBaseline, 2) }}
                        </p>
                        <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">avg last 6 months</p>
                    </div>

                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">3-month target</p>
                        <p class="mt-1 text-2xl font-semibold font-mono text-gray-800 dark:text-gray-100">
                            ${{ number_format($target3, 2) }}
                        </p>
                    </div>

                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">6-month target</p>
                        <p class="mt-1 text-2xl font-semibold font-mono text-gray-800 dark:text-gray-100">
                            ${{ number_format($target6, 2) }}
                        </p>
                    </div>

                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">You have</p>
                        @if ($currentSavings !== null)
                            <p class="mt-1 text-2xl font-semibold font-mono {{ $currentSavings >= $target3 ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }}">
                                ${{ number_format($currentSavings, 2) }}
                            </p>
                            <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">{{ $emergencyEnvelope->name }}</p>
                        @else
                            <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">—</p>
                            <a href="{{ route('envelopes.index') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Set up &rarr;</a>
                        @endif
                    </div>
                </div>

                {{-- Progress bars --}}
                @if (!empty($bars))
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5 space-y-5">
                        @foreach ($bars as $bar)
                            <div>
                                <div class="flex justify-between text-sm mb-1.5">
                                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ $bar['label'] }}</span>
                                    <span class="font-mono text-gray-600 dark:text-gray-400">
                                        ${{ number_format($currentSavings, 2) }} / ${{ number_format($bar['target'], 2) }}
                                        <span class="text-gray-400 dark:text-gray-500 ml-1">({{ $bar['pct'] }}%)</span>
                                    </span>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2.5 overflow-hidden">
                                    <div class="h-full rounded-full transition-all {{ $currentSavings >= $bar['target'] ? 'bg-green-500' : 'bg-indigo-500' }}"
                                         style="width: {{ $bar['pct'] }}%"></div>
                                </div>
                                @if ($currentSavings < $bar['target'])
                                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                        ${{ number_format($bar['target'] - $currentSavings, 2) }} to go
                                    </p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Mandatory expense breakdown --}}
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Mandatory Expenses</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Average monthly spend over the last 6 months</p>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($monthlyBreakdown as $row)
                            <div class="px-6 py-3 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="inline-block w-2.5 h-2.5 rounded-full shrink-0"
                                          style="background-color: {{ $row['envelope']->color }}"></span>
                                    <a href="{{ route('envelopes.show', $row['envelope']) }}"
                                       class="text-sm text-gray-800 dark:text-gray-200 hover:underline">
                                        {{ $row['envelope']->name }}
                                    </a>
                                </div>
                                <span class="text-sm font-mono text-gray-700 dark:text-gray-300">
                                    ${{ number_format($row['avg'], 2) }}/mo
                                </span>
                            </div>
                        @endforeach
                        <div class="px-6 py-3 flex items-center justify-between bg-gray-50 dark:bg-gray-700/40">
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Total</span>
                            <span class="text-sm font-mono font-semibold text-gray-800 dark:text-gray-200">
                                ${{ number_format($monthlyBaseline, 2) }}/mo
                            </span>
                        </div>
                    </div>
                </div>

            @endif

            @if ($mandatoryEnvelopes->isNotEmpty() && $emergencyEnvelope === null)
                <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg px-5 py-4 text-sm text-amber-800 dark:text-amber-300">
                    No envelope is marked as your emergency fund savings. Edit an envelope and check <strong>Emergency fund savings</strong> so the calculator can show your progress.
                </div>
            @endif

        </div>
    </div>
</x-app-layout>

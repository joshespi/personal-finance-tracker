<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Extra-Cash Allocator
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Input form --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <form method="GET" action="{{ route('allocator') }}" class="flex items-end gap-3">
                    <div class="flex-1">
                        <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Extra cash to allocate
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-3 flex items-center text-gray-500 dark:text-gray-400 pointer-events-none">$</span>
                            <input id="amount" name="amount" type="number" min="1" max="10000000" step="0.01"
                                   value="{{ $amount ?? old('amount') }}"
                                   placeholder="500.00"
                                   class="block w-full pl-7 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                        </div>
                        @error('amount')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <x-primary-button type="submit">Allocate</x-primary-button>
                </form>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    Ranked recommendation: emergency fund → high-APR debt → savings goals → remainder.
                </p>
            </div>

            @if ($amount !== null)
                @if (empty($buckets))
                    {{-- All goals funded, no debt --}}
                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg px-5 py-4 text-sm text-green-800 dark:text-green-300">
                        <p class="font-semibold">Nothing to allocate to — looking good.</p>
                        <p class="mt-1">Emergency fund is on track, no revolving debt, and all savings goals are funded. Consider investing the full
                            <span class="font-mono font-bold">${{ number_format($amount, 2) }}</span>.</p>
                    </div>
                @else
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                        <div class="divide-y divide-gray-100 dark:divide-gray-700">
                            @php $rank = 1; @endphp
                            @foreach ($buckets as $bucket)
                                @php
                                    $colors = match ($bucket['type']) {
                                        'emergency' => ['dot' => 'bg-emerald-500', 'badge' => 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-300'],
                                        'debt'      => ['dot' => 'bg-red-500',     'badge' => 'bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300'],
                                        default     => ['dot' => 'bg-indigo-500',  'badge' => 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-800 dark:text-indigo-300'],
                                    };
                                    $fully = $bucket['amount'] >= $bucket['gap'];
                                @endphp
                                <div class="px-6 py-4 flex items-start gap-4">
                                    <div class="flex-shrink-0 w-6 h-6 rounded-full {{ $colors['dot'] }} flex items-center justify-center text-white text-xs font-bold mt-0.5">
                                        {{ $rank++ }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <p class="font-semibold text-gray-900 dark:text-gray-100 truncate">{{ $bucket['label'] }}</p>
                                            <span class="text-xs px-2 py-0.5 rounded-full {{ $colors['badge'] }}">{{ ucfirst($bucket['type']) }}</span>
                                            @if ($fully)
                                                <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400">Fully covered</span>
                                            @endif
                                        </div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $bucket['reason'] }}</p>
                                        @if (! $fully)
                                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                                ${{ number_format($bucket['gap'] - $bucket['amount'], 2) }} still needed after this allocation
                                            </p>
                                        @endif
                                    </div>
                                    <div class="flex-shrink-0 text-right">
                                        <p class="font-mono font-bold text-lg text-gray-900 dark:text-gray-100">${{ number_format($bucket['amount'], 2) }}</p>
                                    </div>
                                </div>
                            @endforeach

                            @if ($remainder > 0)
                                <div class="px-6 py-4 flex items-start gap-4 bg-indigo-50 dark:bg-indigo-900/20">
                                    <div class="flex-shrink-0 w-6 h-6 rounded-full bg-gray-400 dark:bg-gray-500 flex items-center justify-center text-white text-xs font-bold mt-0.5">
                                        &mdash;
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold text-gray-900 dark:text-gray-100">Remainder — invest or discretionary</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">No further priority buckets. Consider investing in a brokerage or Roth IRA.</p>
                                    </div>
                                    <div class="flex-shrink-0 text-right">
                                        <p class="font-mono font-bold text-lg text-gray-900 dark:text-gray-100">${{ number_format($remainder, 2) }}</p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <p class="text-right text-xs text-gray-400 dark:text-gray-500">
                        Total: ${{ number_format($amount, 2) }}
                    </p>
                @endif
            @endif

        </div>
    </div>
</x-app-layout>

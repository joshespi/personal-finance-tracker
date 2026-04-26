<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Budget Envelopes</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Allocate money into envelopes; spend from them as the month goes.</p>
            </div>
            <a href="{{ route('envelopes.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-gray-600 transition">
                + Add Envelope
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="bg-green-100 dark:bg-green-900/40 border border-green-300 dark:border-green-700 text-green-800 dark:text-green-300 rounded-md px-4 py-3 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if ($envelopes->isNotEmpty())
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total in Envelopes</p>
                        <p class="mt-1 text-2xl font-semibold font-mono text-gray-900 dark:text-gray-100">${{ number_format($totalBalance, 2) }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Spent This Month</p>
                        <p class="mt-1 text-2xl font-semibold font-mono text-red-600 dark:text-red-400">−${{ number_format($totalSpentMonth, 2) }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Monthly Target</p>
                        <p class="mt-1 text-2xl font-semibold font-mono text-gray-900 dark:text-gray-100">${{ number_format($totalMonthlyTarget, 2) }}</p>
                    </div>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                @if ($envelopes->isEmpty())
                    <div class="p-6 text-sm text-gray-500 dark:text-gray-400 text-center">No envelopes yet. Create one for each spending category (Groceries, Rent, Fun Money…).</div>
                @else
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($envelopes as $e)
                            @php
                                $target  = (float) ($e->monthly_target ?? 0);
                                $spent   = (float) $e->spent_this_month;
                                $pct     = $target > 0 ? min(100, round($spent / $target * 100)) : 0;
                                $overBudget = $target > 0 && $spent > $target;
                            @endphp
                            <div class="px-6 py-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <span class="inline-block w-3 h-3 rounded-full shrink-0" style="background-color: {{ $e->color }}"></span>
                                        <a href="{{ route('envelopes.show', $e) }}"
                                           class="font-medium text-gray-900 dark:text-gray-100 hover:underline truncate">{{ $e->name }}</a>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <div class="text-right">
                                            <p class="font-mono {{ $e->current_balance >= 0 ? 'text-gray-900 dark:text-gray-100' : 'text-red-600' }} text-sm">
                                                {{ $e->current_balance < 0 ? '−' : '' }}${{ number_format(abs($e->current_balance), 2) }}
                                            </p>
                                            @if ($target > 0)
                                                <p class="text-xs {{ $overBudget ? 'text-red-500' : 'text-gray-400 dark:text-gray-500' }}">
                                                    ${{ number_format($spent, 2) }} / ${{ number_format($target, 2) }} this month
                                                </p>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <a href="{{ route('envelopes.show', $e) }}"
                                               class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">View</a>
                                            <a href="{{ route('envelopes.edit', $e) }}"
                                               class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">Edit</a>
                                            <form method="POST" action="{{ route('envelopes.destroy', $e) }}" class="inline"
                                                  onsubmit="return confirm('Delete this envelope and all its transactions?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-red-600 border border-transparent rounded-md text-xs font-semibold text-white hover:bg-red-500 transition">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                @if ($target > 0)
                                    <div class="mt-3 w-full bg-gray-100 dark:bg-gray-700 rounded-full h-1.5 overflow-hidden">
                                        <div class="h-full rounded-full transition-all"
                                             style="width: {{ $pct }}%; background-color: {{ $overBudget ? '#dc2626' : $e->color }};"></div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

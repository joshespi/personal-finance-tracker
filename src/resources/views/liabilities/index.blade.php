<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Liabilities</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Track debts to get a true net worth picture.</p>
            </div>
            <a href="{{ route('liabilities.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-gray-600 transition">
                + Add Liability
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

            @if ($liabilities->isNotEmpty())
                <x-stat-tile>
                    <x-slot:label>Total Debt</x-slot:label>
                    <p class="mt-1 text-2xl font-semibold font-mono text-red-600 dark:text-red-400">
                        −${{ $demo->amt($totalDebt) }}
                    </p>
                </x-stat-tile>
            @endif

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                @if ($liabilities->isEmpty())
                    <div class="p-6 text-sm text-gray-500 dark:text-gray-400 text-center">No liabilities tracked yet.</div>
                @else
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($liabilities as $l)
                            <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div class="min-w-0">
                                    <a href="{{ route('liabilities.show', $l) }}"
                                       class="font-medium text-gray-900 dark:text-gray-100 hover:underline">{{ $demo->n($l->name) }}</a>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        {{ $liabilityTypes[$l->liability_type] ?? $l->liability_type }}
                                        @if ($l->manualAsset)
                                            &bull; secured by <a href="{{ route('manual-assets.show', $l->manualAsset) }}" class="hover:underline">{{ $demo->n($l->manualAsset->name) }}</a>
                                        @endif
                                        @if ($l->interest_rate !== null)
                                            &bull; {{ rtrim(rtrim(number_format((float)$l->interest_rate, 3), '0'), '.') }}% APR
                                        @endif
                                        &bull; {{ $l->currency }}
                                    </p>
                                </div>
                                <div class="flex items-center justify-between sm:justify-end gap-4 shrink-0">
                                    <div class="text-right">
                                        @if ($l->latestBalance)
                                            <p class="font-mono text-red-600 dark:text-red-400 text-sm">−{{ $demo->amt((float)$l->latestBalance->balance) }}</p>
                                            <p class="text-xs text-gray-400 dark:text-gray-500">{{ $l->latestBalance->recorded_at->format('M j, Y') }}</p>
                                        @else
                                            <span class="text-xs text-gray-400 dark:text-gray-500">No balance</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('liabilities.show', $l) }}"
                                           class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                                            View
                                        </a>
                                        <a href="{{ route('liabilities.edit', $l) }}"
                                           class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                                            Edit
                                        </a>
                                        <form method="POST" action="{{ route('liabilities.destroy', $l) }}" class="inline"
                                              onsubmit="return confirm('Delete this liability and all its balance history?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="inline-flex items-center px-3 py-1.5 bg-red-600 border border-transparent rounded-md text-xs font-semibold text-white hover:bg-red-500 transition">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

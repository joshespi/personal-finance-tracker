<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    <a href="{{ route('portfolios.show', $portfolio) }}" class="hover:underline">{{ $portfolio->name }}</a>
                </p>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Manual Assets</h2>
            </div>
            <a href="{{ route('portfolios.manual-assets.create', $portfolio) }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-gray-600 transition">
                + Add Asset
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 bg-green-100 dark:bg-green-900/40 border border-green-300 dark:border-green-700 text-green-800 dark:text-green-300 rounded-md px-4 py-3 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                @if ($manualAssets->isEmpty())
                    <div class="p-6 text-sm text-gray-500 dark:text-gray-400 text-center">No manual assets yet.</div>
                @else
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($manualAssets as $ma)
                            <div class="px-6 py-4 flex items-center justify-between">
                                <div>
                                    <a href="{{ route('manual-assets.show', $ma) }}"
                                       class="font-medium text-gray-900 dark:text-gray-100 hover:underline">{{ $ma->name }}</a>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        {{ ucwords(str_replace('_', ' ', $ma->asset_class)) }} &bull; {{ $ma->currency }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="text-right">
                                        @if ($ma->latestValuation)
                                            <p class="font-mono text-gray-900 dark:text-gray-100 text-sm">{{ $demo->amt((float) $ma->latestValuation->value) }}</p>
                                            <p class="text-xs text-gray-400 dark:text-gray-500">{{ $ma->latestValuation->valued_at->format('M j, Y') }}</p>
                                        @else
                                            <span class="text-xs text-gray-400 dark:text-gray-500">No valuation</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('manual-assets.show', $ma) }}"
                                           class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                                            View
                                        </a>
                                        <a href="{{ route('manual-assets.edit', $ma) }}"
                                           class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                                            Edit
                                        </a>
                                        <form method="POST" action="{{ route('manual-assets.destroy', $ma) }}" class="inline"
                                              onsubmit="return confirm('Delete this asset?')">
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

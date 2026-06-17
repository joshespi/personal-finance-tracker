<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Edit &mdash; {{ $portfolio->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('portfolios.update', $portfolio) }}" class="space-y-6">
                    @csrf
                    @method('PUT')
                    @include('portfolios._form')
                    <div class="flex items-center gap-4">
                        <x-primary-button>Save Changes</x-primary-button>
                        <a href="{{ route('portfolios.show', $portfolio) }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Cancel</a>
                    </div>
                </form>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 mt-4">
                @if ($portfolio->isClosed())
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Reopen portfolio</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Closed {{ $portfolio->closed_at->format('M j, Y') }}. Reopening returns it to the dashboard and lets you add transactions again.</p>
                    <form method="POST" action="{{ route('portfolios.reopen', $portfolio) }}" class="mt-3">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 transition">
                            Reopen Portfolio
                        </button>
                    </form>
                @else
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Close portfolio</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Archives this portfolio: hidden from the active list, excluded from dashboard net worth, and locked against new transactions. History is kept and it can be reopened anytime.</p>
                    <form method="POST" action="{{ route('portfolios.close', $portfolio) }}" class="mt-3"
                          onsubmit="return confirm('Close this portfolio? It will be archived and excluded from your dashboard.')">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                            Close Portfolio
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

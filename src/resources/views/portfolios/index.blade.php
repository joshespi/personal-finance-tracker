<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Portfolios</h2>
            <a href="{{ route('portfolios.create') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                + New Portfolio
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('success'))
                <div class="bg-green-100 border border-green-300 text-green-800 rounded-md px-4 py-3 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @forelse ($portfolios as $portfolio)
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">
                                <a href="{{ route('portfolios.show', $portfolio) }}" class="hover:underline">
                                    {{ $portfolio->name }}
                                </a>
                            </h3>
                            @if ($portfolio->description)
                                <p class="text-sm text-gray-500 mt-1">{{ $portfolio->description }}</p>
                            @endif
                            <p class="text-xs text-gray-400 mt-2">
                                {{ $portfolio->currency }}
                                &bull; {{ $portfolio->transactions_count }} transaction(s)
                                &bull; {{ $portfolio->manualAssets->count() }} manual asset(s)
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0 ms-4">
                            <a href="{{ route('portfolios.show', $portfolio) }}"
                               class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md text-xs font-semibold text-gray-700 hover:bg-gray-50 transition">
                                View
                            </a>
                            <a href="{{ route('portfolios.edit', $portfolio) }}"
                               class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md text-xs font-semibold text-gray-700 hover:bg-gray-50 transition">
                                Edit
                            </a>
                            <form method="POST" action="{{ route('portfolios.destroy', $portfolio) }}"
                                  onsubmit="return confirm('Delete portfolio and all its data?')">
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
            @empty
                <div class="bg-white shadow-sm sm:rounded-lg p-6 text-center text-gray-500">
                    No portfolios yet.
                    <a href="{{ route('portfolios.create') }}" class="text-indigo-600 hover:underline ms-1">Create one.</a>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    <a href="{{ route('portfolios.show', $portfolio) }}" class="hover:underline">{{ $portfolio->name }}</a>
                </p>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Transactions</h2>
            </div>
            <div class="flex items-center gap-2" x-data="{ importOpen: false }">
                <a href="{{ route('transfers.create') }}"
                   class="hidden sm:inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                    Portfolio Transfer
                </a>
                <button @click="importOpen = true"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                    Import CSV
                </button>
                <a href="{{ route('portfolios.transactions.create', $portfolio) }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-gray-600 transition">
                    + Add Transaction
                </a>

                {{-- Import modal --}}
                <div x-show="importOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
                    <div class="absolute inset-0 bg-black/40" @click="importOpen = false"></div>
                    <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md mx-4 p-6" @click.stop>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-1">Import Transactions</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                            Upload a CSV file.
                            <a href="{{ route('portfolios.transactions.import.template', $portfolio) }}"
                               class="text-indigo-600 dark:text-indigo-400 hover:underline">Download template</a>
                        </p>

                        @if ($errors->has('csv_file'))
                            <div class="mb-4 bg-red-50 dark:bg-red-900/40 border border-red-200 dark:border-red-700 text-red-700 dark:text-red-300 rounded-md px-4 py-3 text-sm space-y-1">
                                @foreach ((array) $errors->get('csv_file') as $err)
                                    @foreach ((array) $err as $msg)
                                        <p>{{ $msg }}</p>
                                    @endforeach
                                @endforeach
                            </div>
                        @endif

                        <form method="POST"
                              action="{{ route('portfolios.transactions.import', $portfolio) }}"
                              enctype="multipart/form-data">
                            @csrf
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">CSV File</label>
                                <input type="file" name="csv_file" accept=".csv,.txt" required
                                       class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-3 file:py-1.5 file:px-4 file:rounded file:border file:border-gray-300 dark:file:border-gray-600 file:text-xs file:font-semibold file:text-gray-700 dark:file:text-gray-300 file:bg-white dark:file:bg-gray-700 hover:file:bg-gray-50 dark:hover:file:bg-gray-600">
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="importOpen = false"
                                        class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200">Cancel</button>
                                <button type="submit"
                                        class="px-4 py-2 bg-gray-800 dark:bg-gray-700 text-white text-sm rounded-md hover:bg-gray-700 dark:hover:bg-gray-600 transition">
                                    Import
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="sm:px-6 lg:px-8 space-y-4">

            <x-flash />

            @include('transactions._list', [
                'formUrl'   => route('portfolios.transactions.index', $portfolio),
                'clearKeys' => ['search', 'type', 'from', 'to'],
            ])
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Import transactions from CSV</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if ($errors->any())
                <div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 rounded-lg px-5 py-4">
                    @foreach ($errors->all() as $error)
                        <p class="text-sm text-red-700 dark:text-red-300">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 space-y-5">
                <div>
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">Upload any transaction CSV</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        Upload a CSV export from YNAB, Mint, your bank, or anywhere else. On the next screen
                        you'll map each column (date, amount, account…) to the matching field — recognised
                        formats like YNAB are detected and pre-filled for you.
                    </p>
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                        <strong>YNAB:</strong> Export → Export Budget → <strong>All Transactions</strong>, then upload the CSV here.
                    </p>
                </div>

                <form method="POST" action="{{ route('import.csv.upload') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">CSV file</label>
                        <input type="file" name="csv_file" accept=".csv,.txt" required
                               class="block w-full text-sm text-gray-600 dark:text-gray-400
                                      file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0
                                      file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700
                                      dark:file:bg-indigo-900/40 dark:file:text-indigo-300
                                      hover:file:bg-indigo-100 dark:hover:file:bg-indigo-900/60">
                    </div>
                    <x-primary-button type="submit">Parse & Map</x-primary-button>
                </form>
            </div>

            <p class="text-xs text-gray-400 dark:text-gray-500 px-1">
                Your file is parsed server-side and stored temporarily while you review. Nothing is saved until you confirm the import.
                <a href="{{ route('export.index') }}" class="hover:underline">← Back to Export & Backup</a>
            </p>

        </div>
    </div>
</x-app-layout>

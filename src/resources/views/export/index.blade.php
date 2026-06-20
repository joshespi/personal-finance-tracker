<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Export & Backup</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            {{-- Full backup --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 flex items-start justify-between gap-6">
                <div>
                    <p class="font-semibold text-gray-900 dark:text-gray-100">Full Backup <span class="ml-1.5 text-xs px-2 py-0.5 rounded-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300">JSON</span></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Every portfolio, transaction, envelope, spending account, liability, income entry, and scheduled transaction — all in one file.
                        Download regularly and store somewhere safe.
                    </p>
                </div>
                <a href="{{ route('export.backup') }}"
                   class="shrink-0 inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md transition">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Download
                </a>
            </div>

            {{-- Transactions CSV --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 flex items-start justify-between gap-6">
                <div>
                    <p class="font-semibold text-gray-900 dark:text-gray-100">Transactions <span class="ml-1.5 text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">CSV</span></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">All buy / sell / dividend transactions across every portfolio. Useful for importing into a spreadsheet or another app.</p>
                </div>
                <a href="{{ route('export.transactions') }}"
                   class="shrink-0 inline-flex items-center gap-1.5 px-4 py-2 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-md border border-gray-300 dark:border-gray-600 transition">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Download
                </a>
            </div>

            {{-- Realized gains CSV --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 flex items-start justify-between gap-6">
                <div>
                    <p class="font-semibold text-gray-900 dark:text-gray-100">Realized Gains <span class="ml-1.5 text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">CSV</span></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Closed tax lots across all non-tax-advantaged portfolios. Includes buy date, sell date, holding period, and gain / loss per lot.</p>
                </div>
                <a href="{{ route('export.realized-gains') }}"
                   class="shrink-0 inline-flex items-center gap-1.5 px-4 py-2 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-md border border-gray-300 dark:border-gray-600 transition">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Download
                </a>
            </div>

            {{-- CSV import --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 flex items-start justify-between gap-6 border-t-2 border-indigo-100 dark:border-indigo-900/40 mt-4">
                <div>
                    <p class="font-semibold text-gray-900 dark:text-gray-100">Import transactions <span class="ml-1.5 text-xs px-2 py-0.5 rounded-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300">CSV</span></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Import transactions from any CSV — YNAB, Mint, your bank, and more. Upload the file,
                        map its columns to the matching fields (YNAB is auto-detected), and import into your
                        spending accounts with a preview before committing.
                    </p>
                </div>
                <a href="{{ route('import.csv') }}"
                   class="shrink-0 inline-flex items-center gap-1.5 px-4 py-2 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-md border border-gray-300 dark:border-gray-600 transition">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                    </svg>
                    Import
                </a>
            </div>

            <p class="text-xs text-gray-400 dark:text-gray-500 px-1">
                All exports contain only your own data. No telemetry. Store the full backup somewhere off-server (local disk, cloud storage) so you have a copy independent of the database.
            </p>

        </div>
    </div>
</x-app-layout>

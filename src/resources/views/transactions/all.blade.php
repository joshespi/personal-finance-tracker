<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">All Transactions</h2>
            <x-btn variant="secondary" href="{{ route('export.transactions') }}">
                ↓ Export CSV
            </x-btn>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="sm:px-6 lg:px-8 space-y-4">

            <x-flash />

            @include('transactions._list', [
                'formUrl'   => route('transactions.all'),
                'clearKeys' => ['search', 'type', 'from', 'to', 'portfolio_id'],
                'portfolios' => $portfolios,
            ])
        </div>
    </div>
</x-app-layout>

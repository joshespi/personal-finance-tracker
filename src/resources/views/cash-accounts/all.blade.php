<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    <a href="{{ route('cash-accounts.index') }}" class="hover:underline">Spending Accounts</a>
                </p>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">All Transactions</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Every account's activity, plus what's scheduled next.</p>
            </div>
            <div class="flex items-center gap-2">
                <x-btn variant="secondary" href="{{ route('cash-transfers.create') }}">
                    Transfer
                </x-btn>
                <x-btn variant="secondary" href="{{ route('cash-accounts.index') }}">
                    Accounts
                </x-btn>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="sm:px-6 lg:px-8 space-y-8">

            <x-flash />

            <livewire:all-transactions />
        </div>
    </div>
</x-app-layout>

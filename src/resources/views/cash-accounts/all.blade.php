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
            <a href="{{ route('cash-accounts.index') }}"
               class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                Accounts
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            <livewire:all-transactions />
        </div>
    </div>
</x-app-layout>

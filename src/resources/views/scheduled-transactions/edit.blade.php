<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                <a href="{{ route('scheduled-transactions.index') }}" class="hover:underline">Scheduled Transactions</a>
            </p>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight mt-0.5">Edit Scheduled Transaction</h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('scheduled-transactions.update', $scheduledTransaction) }}" class="space-y-6">
                    @csrf
                    @method('PUT')
                    @include('scheduled-transactions._form')
                    <div class="flex items-center gap-3 pt-2">
                        <x-primary-button>Save</x-primary-button>
                        <a href="{{ route('scheduled-transactions.index') }}"
                           class="text-sm text-gray-500 dark:text-gray-400 hover:underline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

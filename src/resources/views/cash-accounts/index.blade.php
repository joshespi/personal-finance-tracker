<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Spending Accounts</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Checking, savings, credit cards, and other spending accounts.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('cash-transfers.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                    Transfer
                </a>
                <a href="{{ route('cash-accounts.all') }}"
                   class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                    All Transactions
                </a>
                <a href="{{ route('cash-accounts.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-gray-600 transition">
                    + Add Account
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="bg-green-100 dark:bg-green-900/40 border border-green-300 dark:border-green-700 text-green-800 dark:text-green-300 rounded-md px-4 py-3 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if ($accounts->isNotEmpty())
                <x-stat-tile>
                    <x-slot:label>Total Balance</x-slot:label>
                    <p class="mt-1 text-2xl font-semibold font-mono text-gray-900 dark:text-gray-100">
                        ${{ $demo->amt($totalCash) }}
                    </p>
                </x-stat-tile>
            @endif

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                @if ($accounts->isEmpty())
                    <div class="p-6 text-sm text-gray-500 dark:text-gray-400 text-center">No spending accounts yet.</div>
                @else
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($accounts as $a)
                            <div class="px-6 py-4 flex items-center justify-between">
                                <div>
                                    <a href="{{ route('cash-accounts.show', $a) }}"
                                       class="font-medium text-gray-900 dark:text-gray-100 hover:underline">{{ $demo->n($a->name) }}</a>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        {{ $accountTypes[$a->account_type] ?? $a->account_type }} &bull; {{ $a->currency }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="text-right">
                                        <p class="font-mono {{ $a->current_balance >= 0 ? 'text-gray-900 dark:text-gray-100' : 'text-red-600' }} text-sm">
                                            {{ $a->current_balance < 0 ? '−' : '' }}${{ $demo->amt(abs($a->current_balance)) }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('cash-accounts.show', $a) }}"
                                           class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                                            View
                                        </a>
                                        <a href="{{ route('cash-accounts.edit', $a) }}"
                                           class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                                            Edit
                                        </a>
                                        <form method="POST" action="{{ route('cash-accounts.destroy', $a) }}" class="inline"
                                              onsubmit="return confirm('Delete this account and all its transactions?')">
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

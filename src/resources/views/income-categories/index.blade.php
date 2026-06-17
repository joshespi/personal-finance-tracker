<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Income Categories</h2>
            <a href="{{ route('income-categories.create') }}"
               class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded-md text-xs font-semibold text-white hover:bg-indigo-500 transition">
                + New Category
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="bg-green-100 dark:bg-green-900/40 border border-green-300 dark:border-green-700 text-green-800 dark:text-green-300 rounded-md px-4 py-3 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <p class="text-sm text-gray-500 dark:text-gray-400">
                Tag deposits with a category to tell apart sources of income — salary, bonus, gifts, refunds, interest, and so on.
                Choose a category when recording a deposit on a spending account.
            </p>

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                @if ($categories->isEmpty())
                    <div class="p-6 text-sm text-gray-500 dark:text-gray-400">
                        No income categories yet.
                        <a href="{{ route('income-categories.create') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Create one</a>
                        to start categorizing your deposits.
                    </div>
                @else
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($categories as $category)
                            <div class="px-6 py-4 flex items-center gap-4">
                                <span class="inline-block w-3 h-3 rounded-full shrink-0" style="background-color: {{ $category->color }}"></span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $category->name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        {{ $category->cash_transactions_count }} {{ \Illuminate\Support\Str::plural('deposit', $category->cash_transactions_count) }} tagged
                                    </p>
                                </div>
                                <a href="{{ route('income-categories.edit', $category) }}"
                                   class="text-xs text-gray-500 dark:text-gray-400 hover:underline">Edit</a>
                                <form method="POST" action="{{ route('income-categories.destroy', $category) }}"
                                      onsubmit="return confirm('Delete this category? Deposits tagged with it will become uncategorized.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-600 dark:text-red-400 hover:underline">Delete</button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

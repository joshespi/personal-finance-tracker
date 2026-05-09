<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Scheduled Transactions</h2>
            <a href="{{ route('scheduled-transactions.create') }}"
               class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded-md text-xs font-semibold text-white hover:bg-indigo-500 transition">
                + New
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="bg-green-100 dark:bg-green-900/40 border border-green-300 dark:border-green-700 text-green-800 dark:text-green-300 rounded-md px-4 py-3 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if ($count > 0)
                <div class="bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-700 text-indigo-800 dark:text-indigo-300 rounded-md px-4 py-3 text-sm">
                    {{ $count }} scheduled {{ Str::plural('transaction', $count) }} materialized.
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                @if ($scheduled->isEmpty())
                    <div class="p-6 text-sm text-gray-500 dark:text-gray-400">
                        No scheduled transactions yet.
                        <a href="{{ route('scheduled-transactions.create') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Create one</a>
                        to auto-record recurring income and expenses.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                                    <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Amount</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Recurrence</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Next Due</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Linked To</th>
                                    <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Active</th>
                                    <th class="px-5 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($scheduled as $s)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 {{ !$s->is_active ? 'opacity-50' : '' }}">
                                        <td class="px-5 py-3 text-gray-800 dark:text-gray-200 font-medium">{{ $s->description }}</td>
                                        <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ $s->typeLabel() }}</td>
                                        <td class="px-5 py-3 text-right font-mono text-gray-800 dark:text-gray-200">${{ number_format((float)$s->amount, 2) }}</td>
                                        <td class="px-5 py-3 text-gray-500 dark:text-gray-400">{{ $s->recurrenceLabel() }}</td>
                                        <td class="px-5 py-3 whitespace-nowrap {{ $s->is_active && $s->next_due_at->isPast() ? 'text-amber-600 dark:text-amber-400 font-medium' : 'text-gray-500 dark:text-gray-400' }}">
                                            {{ $s->next_due_at->format('M j, Y') }}
                                        </td>
                                        <td class="px-5 py-3 text-gray-500 dark:text-gray-400 text-xs">
                                            @if ($s->envelope)
                                                <span class="inline-block w-2 h-2 rounded-full mr-1 align-middle" style="background-color: {{ $s->envelope->color }}"></span>{{ $s->envelope->name }}
                                            @endif
                                            @if ($s->cashAccount)
                                                @if ($s->envelope) <br> @endif
                                                {{ $s->cashAccount->name }}
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-center">
                                            <form method="POST" action="{{ route('scheduled-transactions.toggle', $s) }}" class="inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit"
                                                        class="w-9 h-5 rounded-full transition-colors focus:outline-none {{ $s->is_active ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-gray-600' }}"
                                                        title="{{ $s->is_active ? 'Pause' : 'Resume' }}">
                                                    <span class="sr-only">{{ $s->is_active ? 'Active' : 'Paused' }}</span>
                                                </button>
                                            </form>
                                        </td>
                                        <td class="px-5 py-3 text-right whitespace-nowrap">
                                            <a href="{{ route('scheduled-transactions.edit', $s) }}"
                                               class="text-indigo-600 dark:text-indigo-400 hover:underline text-xs mr-3">Edit</a>
                                            <form method="POST" action="{{ route('scheduled-transactions.destroy', $s) }}" class="inline"
                                                  onsubmit="return confirm('Delete this scheduled transaction?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 dark:text-red-400 hover:underline text-xs">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <p class="text-xs text-gray-400 dark:text-gray-500">
                Due transactions are automatically recorded when you visit this page.
                Paused transactions are skipped until re-enabled.
            </p>
        </div>
    </div>
</x-app-layout>

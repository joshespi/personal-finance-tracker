<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    <a href="{{ route('liabilities.index') }}" class="hover:underline">Liabilities</a>
                </p>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ $liability->name }}</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ $liabilityTypes[$liability->liability_type] ?? $liability->liability_type }}
                    @if ($liability->manualAsset)
                        &bull; secured by <a href="{{ route('manual-assets.show', $liability->manualAsset) }}" class="hover:underline">{{ $liability->manualAsset->name }}</a>
                    @endif
                    @if ($liability->interest_rate !== null)
                        &bull; {{ rtrim(rtrim(number_format((float)$liability->interest_rate, 3), '0'), '.') }}% APR
                    @endif
                    &bull; {{ $liability->currency }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('liabilities.edit', $liability) }}"
                   class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                    Edit
                </a>
                <form method="POST" action="{{ route('liabilities.destroy', $liability) }}"
                      onsubmit="return confirm('Delete this liability and all its balance history?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="inline-flex items-center px-3 py-1.5 bg-red-600 border border-transparent rounded-md text-xs font-semibold text-white hover:bg-red-500 transition">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @if (session('success'))
                <div class="bg-green-100 dark:bg-green-900/40 border border-green-300 dark:border-green-700 text-green-800 dark:text-green-300 rounded-md px-4 py-3 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if ($liability->notes)
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $liability->notes }}</p>
                </div>
            @endif

            {{-- Add Balance --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Record New Balance</h3>
                </div>
                <div class="p-6">
                    <form method="POST" action="{{ route('liabilities.balances.store', $liability) }}"
                          class="flex flex-wrap items-end gap-4">
                        @csrf

                        <div>
                            <x-input-label for="balance" value="Balance Owed ({{ $liability->currency }})" />
                            <x-text-input id="balance" name="balance" type="number" class="mt-1 block w-40"
                                          :value="old('balance')" required min="0" step="any" placeholder="245000.00" />
                            <x-input-error :messages="$errors->get('balance')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="recorded_at" value="Date" />
                            <x-text-input id="recorded_at" name="recorded_at" type="date" class="mt-1 block w-40"
                                          :value="old('recorded_at', now()->format('Y-m-d'))" required />
                            <x-input-error :messages="$errors->get('recorded_at')" class="mt-2" />
                        </div>

                        <div class="flex-1 min-w-48">
                            <x-input-label for="notes" value="Notes (optional)" />
                            <x-text-input id="notes" name="notes" type="text" class="mt-1 block w-full"
                                          :value="old('notes')" maxlength="1000" placeholder="Monthly statement" />
                            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                        </div>

                        <div class="pb-0.5">
                            <x-primary-button>Record</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Balance History --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Balance History</h3>
                </div>

                @if ($liability->balances->isEmpty())
                    <div class="p-6 text-sm text-gray-500 dark:text-gray-400">No balances recorded yet.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Balance ({{ $liability->currency }})</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Notes</th>
                                    <th class="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($liability->balances as $b)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-6 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $b->recorded_at->format('M j, Y') }}</td>
                                        <td class="px-6 py-3 text-right font-mono font-semibold text-red-600 dark:text-red-400">−{{ number_format((float)$b->balance, 2) }}</td>
                                        <td class="px-6 py-3 text-gray-500 dark:text-gray-400">{{ $b->notes ?? '—' }}</td>
                                        <td class="px-6 py-3 text-right">
                                            <form method="POST" action="{{ route('liabilities.balances.destroy', $b) }}" class="inline"
                                                  onsubmit="return confirm('Delete this balance entry?')">
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

        </div>
    </div>
</x-app-layout>

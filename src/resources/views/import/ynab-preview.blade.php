<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">YNAB Import Preview</h2>
            <form method="POST" action="{{ route('import.ynab.cancel') }}">
                @csrf
                <button type="submit" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                    Cancel import
                </button>
            </form>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if ($errors->any())
                <div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 rounded-lg px-5 py-4">
                    @foreach ($errors->all() as $error)
                        <p class="text-sm text-red-700 dark:text-red-300">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            {{-- Summary tile --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-4 flex items-center gap-6">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Transactions found</p>
                    <p class="text-2xl font-semibold font-mono text-gray-900 dark:text-gray-100">{{ number_format(count($rows)) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Accounts in file</p>
                    <p class="text-2xl font-semibold font-mono text-gray-900 dark:text-gray-100">{{ $ynabAccounts->count() }}</p>
                </div>
                @php $dateRange = collect($rows)->pluck('date'); @endphp
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Date range</p>
                    <p class="text-sm font-mono text-gray-700 dark:text-gray-300">
                        {{ $dateRange->min() }} → {{ $dateRange->max() }}
                    </p>
                </div>
            </div>

            {{-- Account mapping --}}
            <form method="POST" action="{{ route('import.ynab.commit') }}">
                @csrf

                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Map accounts</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Choose an existing spending account or create a new one for each YNAB account. Skip any you don't want to import.
                        </p>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($ynabAccounts as $ynabName)
                            @php
                                $txCount = collect($rows)->where('account', $ynabName)->count();
                            @endphp
                            <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center gap-3">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $ynabName }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $txCount }} transaction{{ $txCount !== 1 ? 's' : '' }}</p>
                                </div>
                                <div class="sm:w-64 shrink-0">
                                    <select name="account_map[{{ $ynabName }}]"
                                            class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="new">+ Create new account "{{ Str::limit($ynabName, 30) }}"</option>
                                        @foreach ($userAccounts as $ua)
                                            <option value="{{ $ua->id }}">{{ $ua->name }}</option>
                                        @endforeach
                                        <option value="skip">— Skip this account</option>
                                    </select>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Transaction preview --}}
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden mt-6">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Transaction preview</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">First 20 rows</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm divide-y divide-gray-100 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Account</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Amount</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach (array_slice($rows, 0, 20) as $row)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300 truncate max-w-[8rem]">{{ $row['account'] }}</td>
                                        <td class="px-4 py-2 font-mono text-gray-500 dark:text-gray-400">{{ $row['date'] }}</td>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300 truncate max-w-[14rem]">
                                            {{ $row['payee'] }}
                                            @if ($row['category'])
                                                <span class="text-xs text-gray-400 dark:text-gray-500"> · {{ $row['category'] }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-right font-mono">${{ number_format($row['amount'], 2) }}</td>
                                        <td class="px-4 py-2">
                                            <span class="text-xs px-1.5 py-0.5 rounded {{ $row['type'] === 'deposit' ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' }}">
                                                {{ $row['type'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if (count($rows) > 20)
                        <p class="px-6 py-3 text-xs text-gray-400 dark:text-gray-500 border-t border-gray-100 dark:border-gray-700">
                            … and {{ number_format(count($rows) - 20) }} more rows not shown.
                        </p>
                    @endif
                </div>

                <div class="mt-6 flex items-center gap-4">
                    <x-primary-button type="submit">
                        Import {{ number_format(count($rows)) }} transactions
                    </x-primary-button>
                    <form method="POST" action="{{ route('import.ynab.cancel') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition">
                            Cancel
                        </button>
                    </form>
                </div>
            </form>

        </div>
    </div>
</x-app-layout>

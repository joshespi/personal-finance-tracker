<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    <a href="{{ route('portfolios.show', $portfolio) }}" class="hover:underline">{{ $portfolio->name }}</a>
                </p>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Transactions</h2>
            </div>
            <div class="flex items-center gap-2" x-data="{ importOpen: false }">
                <a href="{{ route('transfers.create') }}"
                   class="hidden sm:inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                    Portfolio Transfer
                </a>
                <button @click="importOpen = true"
                        class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                    Import CSV
                </button>
                <a href="{{ route('portfolios.transactions.create', $portfolio) }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-gray-600 transition">
                    + Add Transaction
                </a>

                {{-- Import modal --}}
                <div x-show="importOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
                    <div class="absolute inset-0 bg-black/40" @click="importOpen = false"></div>
                    <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md mx-4 p-6" @click.stop>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-1">Import Transactions</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                            Upload a CSV file.
                            <a href="{{ route('portfolios.transactions.import.template', $portfolio) }}"
                               class="text-indigo-600 dark:text-indigo-400 hover:underline">Download template</a>
                        </p>

                        @if ($errors->has('csv_file'))
                            <div class="mb-4 bg-red-50 dark:bg-red-900/40 border border-red-200 dark:border-red-700 text-red-700 dark:text-red-300 rounded-md px-4 py-3 text-sm space-y-1">
                                @foreach ((array) $errors->get('csv_file') as $err)
                                    @foreach ((array) $err as $msg)
                                        <p>{{ $msg }}</p>
                                    @endforeach
                                @endforeach
                            </div>
                        @endif

                        <form method="POST"
                              action="{{ route('portfolios.transactions.import', $portfolio) }}"
                              enctype="multipart/form-data">
                            @csrf
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">CSV File</label>
                                <input type="file" name="csv_file" accept=".csv,.txt" required
                                       class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-3 file:py-1.5 file:px-4 file:rounded file:border file:border-gray-300 dark:file:border-gray-600 file:text-xs file:font-semibold file:text-gray-700 dark:file:text-gray-300 file:bg-white dark:file:bg-gray-700 hover:file:bg-gray-50 dark:hover:file:bg-gray-600">
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="importOpen = false"
                                        class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200">Cancel</button>
                                <button type="submit"
                                        class="px-4 py-2 bg-gray-800 dark:bg-gray-700 text-white text-sm rounded-md hover:bg-gray-700 dark:hover:bg-gray-600 transition">
                                    Import
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('success'))
                <div class="bg-green-100 dark:bg-green-900/40 border border-green-300 dark:border-green-700 text-green-800 dark:text-green-300 rounded-md px-4 py-3 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Filter bar --}}
            <form method="GET" action="{{ route('portfolios.transactions.index', $portfolio) }}"
                  class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4 flex flex-wrap gap-3 items-end">
                <input type="hidden" name="sort" value="{{ request('sort') }}">
                <input type="hidden" name="dir"  value="{{ request('dir') }}">

                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Symbol</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="BTC, AAPL…"
                           class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md text-sm h-9 px-3 focus:ring-0 focus:border-gray-400 dark:focus:border-gray-500 w-32">
                </div>

                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Type</label>
                    <select name="type" class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md text-sm h-9 px-3 focus:ring-0 focus:border-gray-400 dark:focus:border-gray-500">
                        <option value="">All</option>
                        @foreach (\App\Enums\TransactionType::options() as $key => $label)
                            <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">From</label>
                    <input type="date" name="from" value="{{ request('from') }}"
                           class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md text-sm h-9 px-3 focus:ring-0 focus:border-gray-400 dark:focus:border-gray-500">
                </div>

                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">To</label>
                    <input type="date" name="to" value="{{ request('to') }}"
                           class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md text-sm h-9 px-3 focus:ring-0 focus:border-gray-400 dark:focus:border-gray-500">
                </div>

                <button type="submit"
                        class="h-9 px-4 bg-gray-800 dark:bg-gray-700 text-white text-sm rounded-md hover:bg-gray-700 dark:hover:bg-gray-600 transition">
                    Filter
                </button>

                @if (request()->hasAny(['search', 'type', 'from', 'to']))
                    <a href="{{ route('portfolios.transactions.index', $portfolio) }}"
                       class="h-9 px-4 flex items-center text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                        Clear
                    </a>
                @endif
            </form>

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                @if ($transactions->isEmpty())
                    <div class="p-6 text-sm text-gray-500 dark:text-gray-400 text-center">No transactions found.</div>
                @else
                    <div class="overflow-x-auto">
                        @php
                            $flipDir = fn($col) => ($sortCol === $col && $sortDir === 'asc') ? 'desc' : 'asc';
                            $sortUrl = fn($col) => request()->fullUrlWithQuery(['sort' => $col, 'dir' => $flipDir($col)]);
                            $arrow   = fn($col) => $sortCol === $col ? ($sortDir === 'asc' ? ' ↑' : ' ↓') : '';
                        @endphp
                        <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                        <a href="{{ $sortUrl('transacted_at') }}" class="hover:text-gray-800 dark:hover:text-gray-200">Date{{ $arrow('transacted_at') }}</a>
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                        <a href="{{ $sortUrl('symbol') }}" class="hover:text-gray-800 dark:hover:text-gray-200">Symbol{{ $arrow('symbol') }}</a>
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                        <a href="{{ $sortUrl('type') }}" class="hover:text-gray-800 dark:hover:text-gray-200">Type{{ $arrow('type') }}</a>
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                        <a href="{{ $sortUrl('quantity') }}" class="hover:text-gray-800 dark:hover:text-gray-200">Quantity{{ $arrow('quantity') }}</a>
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Price/Unit</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Fees</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">CCY</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($transactions as $t)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $t->transacted_at->format('M j, Y') }}</td>
                                        <td class="px-4 py-3 font-mono font-semibold text-gray-900 dark:text-gray-100">{{ $t->asset->symbol }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $t->type->badgeClasses() }}">
                                                {{ $t->type->label() }}
                                            </span>
                                            @if ($t->type === \App\Enums\TransactionType::TransferIn && $t->linkedFrom)
                                                <span class="block text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                                    ← {{ $t->linkedFrom->portfolio->name }}
                                                </span>
                                            @elseif ($t->type === \App\Enums\TransactionType::TransferOut && $t->linkedTo)
                                                <span class="block text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                                    → {{ $t->linkedTo->portfolio->name }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono text-gray-900 dark:text-gray-100">
                                            {{ rtrim(rtrim(number_format((float)$t->quantity, 8), '0'), '.') }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono text-gray-700 dark:text-gray-300">{{ number_format((float)$t->price_per_unit, 4) }}</td>
                                        <td class="px-4 py-3 text-right font-mono text-gray-500 dark:text-gray-400">{{ number_format((float)$t->fees, 4) }}</td>
                                        <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900 dark:text-gray-100">{{ number_format($t->totalCost(), 2) }}</td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs">{{ $t->currency }}</td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <a href="{{ route('transactions.edit', $t) }}"
                                               class="text-indigo-600 dark:text-indigo-400 hover:underline text-xs me-3">Edit</a>
                                            <form method="POST" action="{{ route('transactions.destroy', $t) }}" class="inline"
                                                  onsubmit="return confirm('Delete this transaction?')">
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
                    @if ($transactions->hasPages())
                        <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-700">{{ $transactions->links() }}</div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

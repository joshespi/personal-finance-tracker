<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">
                    <a href="{{ route('portfolios.show', $portfolio) }}" class="hover:underline">{{ $portfolio->name }}</a>
                </p>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Transactions</h2>
            </div>
            <div class="flex items-center gap-2" x-data="{ importOpen: false }">
                <button @click="importOpen = true"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition">
                    Import CSV
                </button>
                <a href="{{ route('portfolios.transactions.create', $portfolio) }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                    + Add Transaction
                </a>

                {{-- Import modal --}}
                <div x-show="importOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
                    <div class="absolute inset-0 bg-black/40" @click="importOpen = false"></div>
                    <div class="relative bg-white rounded-lg shadow-xl w-full max-w-md mx-4 p-6" @click.stop>
                        <h3 class="text-base font-semibold text-gray-900 mb-1">Import Transactions</h3>
                        <p class="text-sm text-gray-500 mb-4">
                            Upload a CSV file.
                            <a href="{{ route('portfolios.transactions.import.template', $portfolio) }}"
                               class="text-indigo-600 hover:underline">Download template</a>
                        </p>

                        @if ($errors->has('csv_file'))
                            <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-md px-4 py-3 text-sm space-y-1">
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
                                <label class="block text-sm font-medium text-gray-700 mb-1">CSV File</label>
                                <input type="file" name="csv_file" accept=".csv,.txt" required
                                       class="block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-4 file:rounded file:border file:border-gray-300 file:text-xs file:font-semibold file:text-gray-700 file:bg-white hover:file:bg-gray-50">
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="importOpen = false"
                                        class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">Cancel</button>
                                <button type="submit"
                                        class="px-4 py-2 bg-gray-800 text-white text-sm rounded-md hover:bg-gray-700 transition">
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
                <div class="bg-green-100 border border-green-300 text-green-800 rounded-md px-4 py-3 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Filter bar --}}
            <form method="GET" action="{{ route('portfolios.transactions.index', $portfolio) }}"
                  class="bg-white shadow-sm sm:rounded-lg px-5 py-4 flex flex-wrap gap-3 items-end">
                <input type="hidden" name="sort" value="{{ request('sort') }}">
                <input type="hidden" name="dir"  value="{{ request('dir') }}">

                <div>
                    <label class="block text-xs text-gray-500 mb-1">Symbol</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="BTC, AAPL…"
                           class="border-gray-300 rounded-md text-sm h-9 px-3 focus:ring-0 focus:border-gray-400 w-32">
                </div>

                <div>
                    <label class="block text-xs text-gray-500 mb-1">Type</label>
                    <select name="type" class="border-gray-300 rounded-md text-sm h-9 px-3 focus:ring-0 focus:border-gray-400">
                        <option value="">All</option>
                        @foreach (\App\Http\Controllers\TransactionController::TYPES as $key => $label)
                            <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs text-gray-500 mb-1">From</label>
                    <input type="date" name="from" value="{{ request('from') }}"
                           class="border-gray-300 rounded-md text-sm h-9 px-3 focus:ring-0 focus:border-gray-400">
                </div>

                <div>
                    <label class="block text-xs text-gray-500 mb-1">To</label>
                    <input type="date" name="to" value="{{ request('to') }}"
                           class="border-gray-300 rounded-md text-sm h-9 px-3 focus:ring-0 focus:border-gray-400">
                </div>

                <button type="submit"
                        class="h-9 px-4 bg-gray-800 text-white text-sm rounded-md hover:bg-gray-700 transition">
                    Filter
                </button>

                @if (request()->hasAny(['search', 'type', 'from', 'to']))
                    <a href="{{ route('portfolios.transactions.index', $portfolio) }}"
                       class="h-9 px-4 flex items-center text-sm text-gray-500 hover:text-gray-700">
                        Clear
                    </a>
                @endif
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                @if ($transactions->isEmpty())
                    <div class="p-6 text-sm text-gray-500 text-center">No transactions found.</div>
                @else
                    <div class="overflow-x-auto">
                        @php
                            $flipDir = fn($col) => ($sortCol === $col && $sortDir === 'asc') ? 'desc' : 'asc';
                            $sortUrl = fn($col) => request()->fullUrlWithQuery(['sort' => $col, 'dir' => $flipDir($col)]);
                            $arrow   = fn($col) => $sortCol === $col ? ($sortDir === 'asc' ? ' ↑' : ' ↓') : '';
                        @endphp
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        <a href="{{ $sortUrl('transacted_at') }}" class="hover:text-gray-800">Date{{ $arrow('transacted_at') }}</a>
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        <a href="{{ $sortUrl('symbol') }}" class="hover:text-gray-800">Symbol{{ $arrow('symbol') }}</a>
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        <a href="{{ $sortUrl('type') }}" class="hover:text-gray-800">Type{{ $arrow('type') }}</a>
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                                        <a href="{{ $sortUrl('quantity') }}" class="hover:text-gray-800">Quantity{{ $arrow('quantity') }}</a>
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Price/Unit</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Fees</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">CCY</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @foreach ($transactions as $t)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-gray-700 whitespace-nowrap">{{ $t->transacted_at->format('M j, Y') }}</td>
                                        <td class="px-4 py-3 font-mono font-semibold text-gray-900">{{ $t->asset->symbol }}</td>
                                        <td class="px-4 py-3">
                                            <span @class([
                                                'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                                                'bg-green-100 text-green-800' => in_array($t->type, ['buy', 'transfer_in', 'staking_reward', 'dividend']),
                                                'bg-red-100 text-red-800'     => in_array($t->type, ['sell', 'transfer_out']),
                                            ])>
                                                {{ ucwords(str_replace('_', ' ', $t->type)) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono text-gray-900">
                                            {{ rtrim(rtrim(number_format((float)$t->quantity, 8), '0'), '.') }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono text-gray-700">{{ number_format((float)$t->price_per_unit, 4) }}</td>
                                        <td class="px-4 py-3 text-right font-mono text-gray-500">{{ number_format((float)$t->fees, 4) }}</td>
                                        <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900">{{ number_format($t->totalCost(), 2) }}</td>
                                        <td class="px-4 py-3 text-gray-500 text-xs">{{ $t->currency }}</td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <a href="{{ route('transactions.edit', $t) }}"
                                               class="text-indigo-600 hover:underline text-xs me-3">Edit</a>
                                            <form method="POST" action="{{ route('transactions.destroy', $t) }}" class="inline"
                                                  onsubmit="return confirm('Delete this transaction?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:underline text-xs">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($transactions->hasPages())
                        <div class="px-6 py-4 border-t border-gray-100">{{ $transactions->links() }}</div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

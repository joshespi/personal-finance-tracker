<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">All Transactions</h2>
            <a href="{{ route('export.transactions') }}"
               class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                ↓ Export CSV
            </a>
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
            <form method="GET" action="{{ route('transactions.all') }}"
                  class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4 flex flex-wrap gap-3 items-end">
                <input type="hidden" name="sort" value="{{ request('sort') }}">
                <input type="hidden" name="dir"  value="{{ request('dir') }}">

                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Portfolio</label>
                    <select name="portfolio_id" class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md text-sm h-9 px-3 focus:ring-0 focus:border-gray-400 dark:focus:border-gray-500">
                        <option value="">All</option>
                        @foreach ($portfolios as $p)
                            <option value="{{ $p->id }}" @selected(request('portfolio_id') == $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>

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
                        @foreach (\App\Http\Controllers\TransactionController::TYPES as $key => $label)
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

                @if (request()->hasAny(['search', 'type', 'from', 'to', 'portfolio_id']))
                    <a href="{{ route('transactions.all') }}"
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
                                        <a href="{{ $sortUrl('portfolio') }}" class="hover:text-gray-800 dark:hover:text-gray-200">Portfolio{{ $arrow('portfolio') }}</a>
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
                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400 text-xs">
                                            <a href="{{ route('portfolios.show', $t->portfolio) }}" class="hover:underline">{{ $t->portfolio->name }}</a>
                                        </td>
                                        <td class="px-4 py-3 font-mono font-semibold text-gray-900 dark:text-gray-100">{{ $t->asset->symbol }}</td>
                                        <td class="px-4 py-3">
                                            <span @class([
                                                'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium',
                                                'bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-300' => in_array($t->type, ['buy', 'transfer_in', 'staking_reward', 'dividend']),
                                                'bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-300'         => in_array($t->type, ['sell', 'transfer_out']),
                                            ])>
                                                {{ ucwords(str_replace('_', ' ', $t->type)) }}
                                            </span>
                                            @if ($t->type === 'transfer_in' && $t->linkedFrom)
                                                <span class="block text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                                    ← {{ $t->linkedFrom->portfolio->name }}
                                                </span>
                                            @elseif ($t->type === 'transfer_out' && $t->linkedTo)
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

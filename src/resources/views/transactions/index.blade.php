<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">
                    <a href="{{ route('portfolios.show', $portfolio) }}" class="hover:underline">{{ $portfolio->name }}</a>
                </p>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Transactions</h2>
            </div>
            <a href="{{ route('portfolios.transactions.create', $portfolio) }}"
               class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                + Add Transaction
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 bg-green-100 border border-green-300 text-green-800 rounded-md px-4 py-3 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                @if ($transactions->isEmpty())
                    <div class="p-6 text-sm text-gray-500 text-center">No transactions yet.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Symbol</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Quantity</th>
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

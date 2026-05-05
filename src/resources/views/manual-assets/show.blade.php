<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    <a href="{{ route('portfolios.show', $manualAsset->portfolio) }}" class="hover:underline">
                        {{ $manualAsset->portfolio->name }}
                    </a>
                    &rsaquo; Manual Assets
                </p>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ $manualAsset->name }}</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ $assetClasses[$manualAsset->asset_class] ?? $manualAsset->asset_class }}
                    &bull; {{ $manualAsset->currency }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('manual-assets.edit', $manualAsset) }}"
                   class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                    Edit Asset
                </a>
                <form method="POST" action="{{ route('manual-assets.destroy', $manualAsset) }}"
                      onsubmit="return confirm('Delete this asset and all its valuations?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="inline-flex items-center px-3 py-1.5 bg-red-600 border border-transparent rounded-md text-xs font-semibold text-white hover:bg-red-500 transition">
                        Delete Asset
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

            @if ($manualAsset->description)
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $manualAsset->description }}</p>
                </div>
            @endif

            @if ($manualAsset->cost_basis !== null || $manualAsset->latestValuation)
                @php
                    $cost   = $manualAsset->cost_basis !== null ? (float) $manualAsset->cost_basis : null;
                    $value  = $manualAsset->latestValuation ? (float) $manualAsset->latestValuation->value : null;
                    $pl     = $manualAsset->profitLoss();
                    $plPct  = ($pl !== null && $cost > 0) ? ($pl / $cost) * 100 : null;
                @endphp
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <p class="text-xs uppercase text-gray-500 dark:text-gray-400">Cost Basis</p>
                        <p class="mt-1 font-mono text-lg text-gray-900 dark:text-gray-100">
                            {{ $cost !== null ? number_format($cost, 2) : '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-500 dark:text-gray-400">Current Value</p>
                        <p class="mt-1 font-mono text-lg text-gray-900 dark:text-gray-100">
                            {{ $value !== null ? number_format($value, 2) : '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-500 dark:text-gray-400">Profit / Loss</p>
                        @if ($pl !== null)
                            <p class="mt-1 font-mono text-lg font-semibold {{ $pl >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                {{ $pl >= 0 ? '+' : '' }}{{ number_format($pl, 2) }}
                                @if ($plPct !== null)
                                    <span class="text-xs">({{ $pl >= 0 ? '+' : '' }}{{ number_format($plPct, 2) }}%)</span>
                                @endif
                            </p>
                        @else
                            <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">Add cost basis and a valuation to see P/L.</p>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Linked Liabilities --}}
            @if ($manualAsset->liabilities->isNotEmpty())
                @php
                    $totalDebt = $manualAsset->liabilities->sum(fn ($l) => $l->currentBalance());
                    $equity    = $manualAsset->latestValuation ? (float) $manualAsset->latestValuation->value - $totalDebt : null;
                @endphp
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4 uppercase tracking-wide">Linked Liabilities</h3>
                    <div class="space-y-2">
                        @foreach ($manualAsset->liabilities as $liability)
                            <div class="flex items-center justify-between text-sm">
                                <a href="{{ route('liabilities.show', $liability) }}"
                                   class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $liability->name }}</a>
                                <span class="font-mono text-gray-900 dark:text-gray-100">{{ number_format($liability->currentBalance(), 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                    @if ($equity !== null)
                        <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Equity</span>
                            <span class="font-mono font-semibold {{ $equity >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                {{ number_format($equity, 2) }}
                            </span>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Add Valuation --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Record New Valuation</h3>
                </div>
                <div class="p-6">
                    <form method="POST" action="{{ route('manual-assets.valuations.store', $manualAsset) }}"
                          class="flex flex-wrap items-end gap-4">
                        @csrf

                        <div>
                            <x-input-label for="value" value="Value ({{ $manualAsset->currency }})" />
                            <x-text-input id="value" name="value" type="number" class="mt-1 block w-40"
                                          :value="old('value')" required min="0" step="any" placeholder="250000.00" />
                            <x-input-error :messages="$errors->get('value')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="valued_at" value="Date" />
                            <x-text-input id="valued_at" name="valued_at" type="date" class="mt-1 block w-40"
                                          :value="old('valued_at', now()->format('Y-m-d'))" required />
                            <x-input-error :messages="$errors->get('valued_at')" class="mt-2" />
                        </div>

                        <div class="flex-1 min-w-48">
                            <x-input-label for="notes" value="Notes (optional)" />
                            <x-text-input id="notes" name="notes" type="text" class="mt-1 block w-full"
                                          :value="old('notes')" maxlength="1000" placeholder="Annual appraisal" />
                            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                        </div>

                        <div class="pb-0.5">
                            <x-primary-button>Record</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Valuation History --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Valuation History</h3>
                </div>

                @if ($manualAsset->valuations->isEmpty())
                    <div class="p-6 text-sm text-gray-500 dark:text-gray-400">No valuations recorded yet.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Value ({{ $manualAsset->currency }})</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Notes</th>
                                    <th class="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($manualAsset->valuations as $v)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-6 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $v->valued_at->format('M j, Y') }}</td>
                                        <td class="px-6 py-3 text-right font-mono font-semibold text-gray-900 dark:text-gray-100">{{ number_format((float)$v->value, 2) }}</td>
                                        <td class="px-6 py-3 text-gray-500 dark:text-gray-400">{{ $v->notes ?? '—' }}</td>
                                        <td class="px-6 py-3 text-right">
                                            <form method="POST" action="{{ route('valuations.destroy', $v) }}" class="inline"
                                                  onsubmit="return confirm('Delete this valuation?')">
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

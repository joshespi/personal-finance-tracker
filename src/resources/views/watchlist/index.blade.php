<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Watchlist</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="bg-green-100 dark:bg-green-900/40 border border-green-300 dark:border-green-700 text-green-800 dark:text-green-300 rounded-md px-4 py-3 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Add to watchlist --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">Add to Watchlist</h3>
                <form method="POST" action="{{ route('watchlist.store') }}"
                      x-data="tickerAutocomplete('stock')"
                      @submit.prevent="submitForm($el)"
                      class="grid grid-cols-1 sm:grid-cols-5 gap-3 items-end">
                    @csrf

                    <div class="sm:col-span-1 relative">
                        <x-input-label for="w_symbol" value="Symbol" />
                        <input id="w_symbol" name="symbol" type="text" required maxlength="20"
                               placeholder="AAPL"
                               x-model="query"
                               @input.debounce.300ms="search()"
                               @focus="search()"
                               @keydown.arrow-down.prevent="moveDown()"
                               @keydown.arrow-up.prevent="moveUp()"
                               @keydown.enter.prevent="selectCurrent()"
                               @keydown.escape="close()"
                               @blur="delayClose()"
                               autocomplete="off"
                               class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm uppercase" />
                        <div x-show="open && results.length > 0" x-cloak
                             class="absolute z-50 mt-1 w-64 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-md shadow-lg">
                            <template x-for="(r, i) in results" :key="r.symbol">
                                <div @mousedown.prevent="select(r)"
                                     :class="i === activeIndex ? 'bg-indigo-50 dark:bg-indigo-900/40' : ''"
                                     class="px-3 py-2 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-600 text-sm">
                                    <span class="font-mono font-semibold text-gray-900 dark:text-gray-100" x-text="r.symbol"></span>
                                    <span class="ml-2 text-gray-500 dark:text-gray-400 truncate" x-text="r.name"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="sm:col-span-1">
                        <x-input-label for="w_asset_type" value="Type" />
                        <select id="w_asset_type" name="asset_type"
                                x-model="assetType"
                                @change="results = []; query && search()"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                            <option value="stock">Stock</option>
                            <option value="crypto">Crypto</option>
                            <option value="real_estate">Real Estate</option>
                        </select>
                    </div>

                    <div class="sm:col-span-1">
                        <x-input-label for="w_target_price" value="Target Price" />
                        <x-text-input id="w_target_price" name="target_price" type="number" step="any" min="0"
                                      class="mt-1 block w-full" placeholder="Optional" />
                    </div>

                    <div class="sm:col-span-1">
                        <x-input-label for="w_notes" value="Notes" />
                        <x-text-input id="w_notes" name="notes" type="text" class="mt-1 block w-full" placeholder="Optional" maxlength="500" />
                    </div>

                    <div class="sm:col-span-1">
                        <x-primary-button class="w-full justify-center">Add</x-primary-button>
                    </div>
                </form>
            </div>

            {{-- Watchlist table --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                @if ($items->isEmpty())
                    <div class="p-8 text-center text-gray-500 dark:text-gray-400 text-sm">
                        Your watchlist is empty. Add tickers above to track them.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Symbol</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Name</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                                    <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Current Price</th>
                                    <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Target Price</th>
                                    <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">% to Target</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Notes</th>
                                    <th class="px-5 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($items as $item)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-5 py-3 font-mono font-semibold text-gray-900 dark:text-gray-100">{{ $item->symbol }}</td>
                                        <td class="px-5 py-3 text-gray-700 dark:text-gray-300 max-w-xs truncate">{{ $item->asset_name }}</td>
                                        <td class="px-5 py-3">
                                            @php
                                                $typeClass = match ($item->asset_type) {
                                                    'crypto'      => 'bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300',
                                                    'real_estate' => 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300',
                                                    default       => 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300',
                                                };
                                                $typeLabel = $item->asset_type === 'real_estate' ? 'Real Estate' : ucfirst($item->asset_type);
                                            @endphp
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $typeClass }}">
                                                {{ $typeLabel }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3 text-right font-mono text-gray-900 dark:text-gray-100">
                                            {{ $item->current_price !== null ? '$' . number_format((float)$item->current_price, 4) : '—' }}
                                        </td>
                                        <td class="px-5 py-3 text-right font-mono text-gray-700 dark:text-gray-300">
                                            {{ $item->target_price ? '$' . number_format((float)$item->target_price, 4) : '—' }}
                                        </td>
                                        <td class="px-5 py-3 text-right font-mono font-semibold">
                                            @if ($item->pct_to_target !== null)
                                                <span class="{{ $item->pct_to_target <= 0 ? 'text-green-600' : 'text-amber-600' }}">
                                                    {{ $item->pct_to_target > 0 ? '+' : '' }}{{ $item->pct_to_target }}%
                                                </span>
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-gray-500 dark:text-gray-400 max-w-xs truncate">{{ $item->notes ?: '—' }}</td>
                                        <td class="px-5 py-3 text-right">
                                            <form method="POST" action="{{ route('watchlist.destroy', $item) }}">
                                                @csrf @method('DELETE')
                                                <button type="submit"
                                                        class="text-xs text-red-500 hover:text-red-700 dark:hover:text-red-400"
                                                        onclick="return confirm('Remove {{ $item->symbol }} from watchlist?')">
                                                    Remove
                                                </button>
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

    @push('scripts')
    <script>
    function tickerAutocomplete(defaultType) {
        return {
            query: '',
            assetType: defaultType,
            results: [],
            open: false,
            activeIndex: -1,
            async search() {
                if (this.query.length < 1) { this.results = []; this.open = false; return; }
                try {
                    const res = await fetch(`/tickers/search?q=${encodeURIComponent(this.query)}&type=${this.assetType}`);
                    this.results = await res.json();
                    this.open = this.results.length > 0;
                    this.activeIndex = -1;
                } catch { this.results = []; }
            },
            select(r) {
                this.query = r.symbol;
                this.assetType = r.type;
                this.open = false;
            },
            selectCurrent() {
                if (this.activeIndex >= 0 && this.results[this.activeIndex]) {
                    this.select(this.results[this.activeIndex]);
                }
            },
            moveDown() { this.activeIndex = Math.min(this.activeIndex + 1, this.results.length - 1); },
            moveUp()   { this.activeIndex = Math.max(this.activeIndex - 1, -1); },
            close()    { this.open = false; this.activeIndex = -1; },
            delayClose() { setTimeout(() => this.close(), 150); },
            submitForm(form) {
                // Sync the hidden asset_type select before submitting
                form.querySelector('[name="asset_type"]').value = this.assetType;
                form.submit();
            },
        };
    }
    </script>
    @endpush
</x-app-layout>

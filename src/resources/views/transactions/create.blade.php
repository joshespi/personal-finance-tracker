<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                <a href="{{ route('portfolios.show', $portfolio) }}" class="hover:underline">{{ $portfolio->name }}</a>
            </p>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Add Transaction</h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('portfolios.transactions.store', $portfolio) }}" class="space-y-6"
                      x-data="transactionForm()">
                    @csrf

                    <div class="grid grid-cols-2 gap-4">
                        <div class="relative">
                            <x-input-label for="symbol" value="Symbol" />
                            <input id="symbol" name="symbol" type="text" required maxlength="20"
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
                                   class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm uppercase"
                                   value="{{ old('symbol') }}" />
                            <div x-show="open && results.length > 0" x-cloak
                                 class="absolute z-50 mt-1 w-72 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-md shadow-lg">
                                <template x-for="(r, i) in results" :key="r.symbol">
                                    <div @mousedown.prevent="select(r)"
                                         :class="i === activeIndex ? 'bg-indigo-50 dark:bg-indigo-900/40' : ''"
                                         class="px-3 py-2 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-600 text-sm">
                                        <span class="font-mono font-semibold text-gray-900 dark:text-gray-100" x-text="r.symbol"></span>
                                        <span class="ml-2 text-gray-500 dark:text-gray-400 truncate" x-text="r.name"></span>
                                        <span class="ml-1 text-xs px-1 rounded"
                                              :class="r.type === 'crypto' ? 'text-orange-500' : (r.type === 'real_estate' ? 'text-emerald-500' : 'text-blue-500')"
                                              x-text="r.type"></span>
                                    </div>
                                </template>
                            </div>
                            <x-input-error :messages="$errors->get('symbol')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="asset_type" value="Asset Type" />
                            <select id="asset_type" name="asset_type"
                                    x-model="assetType"
                                    @change="results = []; query && search()"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                @foreach (App\Enums\AssetType::cases() as $type)
                                    <option value="{{ $type->value }}" @selected(old('asset_type', 'stock') === $type->value)>{{ $type->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('asset_type')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="type" value="Transaction Type" />
                        <select id="type" name="type" x-model="txType"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', 'buy') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('type')" class="mt-2" />

                        <div x-show="txType === 'transfer_in' || txType === 'transfer_out'" x-cloak
                             class="mt-2 flex items-start gap-2 rounded-md bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-700 px-3 py-2 text-sm text-indigo-700 dark:text-indigo-300">
                            <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                            </svg>
                            <span>
                                Moving assets between portfolios?
                                <a href="{{ route('transfers.create') }}" class="font-semibold underline hover:text-indigo-900 dark:hover:text-indigo-100">
                                    Use the Portfolio Transfer form
                                </a>
                                to record both sides at once and keep them linked.
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <x-input-label for="quantity" value="Quantity" />
                            <x-text-input id="quantity" name="quantity" type="number" class="mt-1 block w-full"
                                          :value="old('quantity')" required min="0.00000001" step="any" placeholder="10" />
                            <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="price_per_unit" value="Price / Unit" />
                            <x-text-input id="price_per_unit" name="price_per_unit" type="number" class="mt-1 block w-full"
                                          :value="old('price_per_unit')" required min="0" step="any" placeholder="150.00" />
                            <x-input-error :messages="$errors->get('price_per_unit')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="fees" value="Fees" />
                            <x-text-input id="fees" name="fees" type="number" class="mt-1 block w-full"
                                          :value="old('fees', '0')" min="0" step="any" placeholder="0.00" />
                            <x-input-error :messages="$errors->get('fees')" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="currency" value="Currency" />
                            <x-text-input id="currency" name="currency" type="text" class="mt-1 block w-full"
                                          :value="old('currency', $portfolio->currency)" required maxlength="3"
                                          placeholder="USD" style="text-transform:uppercase" />
                            <x-input-error :messages="$errors->get('currency')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="transacted_at" value="Date" />
                            <x-text-input id="transacted_at" name="transacted_at" type="date" class="mt-1 block w-full"
                                          :value="old('transacted_at', now()->format('Y-m-d'))" required />
                            <x-input-error :messages="$errors->get('transacted_at')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="notes" value="Notes (optional)" />
                        <textarea id="notes" name="notes"
                                  class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                  rows="2" maxlength="1000">{{ old('notes') }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>Add Transaction</x-primary-button>
                        <a href="{{ route('portfolios.transactions.index', $portfolio) }}"
                           class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    function transactionForm() {
        return {
            ...tickerSearch({ query: '{{ old('symbol', '') }}', defaultType: '{{ old('asset_type', 'stock') }}', syncSelectId: 'asset_type' }),
            txType: '{{ old('type', 'buy') }}',
        };
    }
    </script>
    @endpush
</x-app-layout>

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
                <form method="POST" action="{{ route('portfolios.transactions.store', $portfolio) }}" class="space-y-6">
                    @csrf

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="symbol" value="Symbol (e.g. AAPL, BTC)" />
                            <x-text-input id="symbol" name="symbol" type="text" class="mt-1 block w-full"
                                          :value="old('symbol')" required autofocus maxlength="20"
                                          placeholder="AAPL" style="text-transform:uppercase" />
                            <x-input-error :messages="$errors->get('symbol')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="asset_type" value="Asset Type" />
                            <select id="asset_type" name="asset_type"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="stock" @selected(old('asset_type') === 'stock')>Stock</option>
                                <option value="crypto" @selected(old('asset_type') === 'crypto')>Crypto</option>
                            </select>
                            <x-input-error :messages="$errors->get('asset_type')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="type" value="Transaction Type" />
                        <select id="type" name="type"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', 'buy') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('type')" class="mt-2" />
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
</x-app-layout>

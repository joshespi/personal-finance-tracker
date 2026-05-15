<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                <a href="{{ route('portfolios.show', $portfolio) }}" class="hover:underline">{{ $portfolio->name }}</a>
                &rsaquo;
                <a href="{{ route('portfolios.transactions.index', $portfolio) }}" class="hover:underline">Transactions</a>
            </p>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Edit Transaction &mdash; {{ $transaction->asset->symbol }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <div class="mb-6 p-3 bg-gray-50 dark:bg-gray-700 rounded-md flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Symbol</p>
                        <p class="font-mono font-bold text-gray-900 dark:text-gray-100 text-lg">{{ $transaction->asset->symbol }}</p>
                    </div>
                    <form method="POST" action="{{ route('assets.reclassify', $transaction->asset) }}" class="flex flex-col items-end gap-1">
                        @csrf
                        @method('PATCH')
                        <label class="text-xs text-gray-500 dark:text-gray-400">Asset type</label>
                        <select name="asset_type" onchange="this.form.submit()"
                                class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm py-1">
                            @foreach ($assetTypes as $type)
                                <option value="{{ $type->value }}" @selected($transaction->asset->asset_type === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>

                <form method="POST" action="{{ route('transactions.update', $transaction) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="type" value="Transaction Type" />
                        <select id="type" name="type"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', $transaction->type) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('type')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <x-input-label for="quantity" value="Quantity" />
                            <x-text-input id="quantity" name="quantity" type="number" class="mt-1 block w-full"
                                          :value="old('quantity', $transaction->quantity)" required min="0.00000001" step="any" />
                            <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="price_per_unit" value="Price / Unit" />
                            <x-text-input id="price_per_unit" name="price_per_unit" type="number" class="mt-1 block w-full"
                                          :value="old('price_per_unit', $transaction->price_per_unit)" required min="0" step="any" />
                            <x-input-error :messages="$errors->get('price_per_unit')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="fees" value="Fees" />
                            <x-text-input id="fees" name="fees" type="number" class="mt-1 block w-full"
                                          :value="old('fees', $transaction->fees)" min="0" step="any" />
                            <x-input-error :messages="$errors->get('fees')" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="currency" value="Currency" />
                            <x-text-input id="currency" name="currency" type="text" class="mt-1 block w-full"
                                          :value="old('currency', $transaction->currency)" required maxlength="3"
                                          style="text-transform:uppercase" />
                            <x-input-error :messages="$errors->get('currency')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="transacted_at" value="Date" />
                            <x-text-input id="transacted_at" name="transacted_at" type="date" class="mt-1 block w-full"
                                          :value="old('transacted_at', $transaction->transacted_at->format('Y-m-d'))" required />
                            <x-input-error :messages="$errors->get('transacted_at')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="notes" value="Notes (optional)" />
                        <textarea id="notes" name="notes"
                                  class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                  rows="2" maxlength="1000">{{ old('notes', $transaction->notes) }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>Save Changes</x-primary-button>
                        <a href="{{ route('portfolios.transactions.index', $portfolio) }}"
                           class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

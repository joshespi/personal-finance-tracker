<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Edit &mdash; {{ $portfolio->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('portfolios.update', $portfolio) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="name" value="Name" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                                      :value="old('name', $portfolio->name)" required autofocus maxlength="100" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="description" value="Description (optional)" />
                        <textarea id="description" name="description"
                                  class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                  rows="3" maxlength="1000">{{ old('description', $portfolio->description) }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="currency" value="Base Currency" />
                        <x-text-input id="currency" name="currency" type="text" class="mt-1 block w-32"
                                      :value="old('currency', $portfolio->currency)" required maxlength="3"
                                      style="text-transform:uppercase" />
                        <x-input-error :messages="$errors->get('currency')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label value="Target Allocation (optional, must sum to 100)" />
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 mb-2">Used to show rebalancing suggestions on your portfolio page. Leave at 0 to disable.</p>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-1">
                            <div>
                                <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Stocks %</label>
                                <x-text-input name="target_stock_pct" type="number" class="mt-1 block w-full"
                                              :value="old('target_stock_pct', $portfolio->target_stock_pct)"
                                              min="0" max="100" step="1" />
                            </div>
                            <div>
                                <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Crypto %</label>
                                <x-text-input name="target_crypto_pct" type="number" class="mt-1 block w-full"
                                              :value="old('target_crypto_pct', $portfolio->target_crypto_pct)"
                                              min="0" max="100" step="1" />
                            </div>
                            <div>
                                <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Real Estate %</label>
                                <x-text-input name="target_real_estate_pct" type="number" class="mt-1 block w-full"
                                              :value="old('target_real_estate_pct', $portfolio->target_real_estate_pct)"
                                              min="0" max="100" step="1" />
                            </div>
                            <div>
                                <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Bonds %</label>
                                <x-text-input name="target_bond_pct" type="number" class="mt-1 block w-full"
                                              :value="old('target_bond_pct', $portfolio->target_bond_pct)"
                                              min="0" max="100" step="1" />
                            </div>
                            <div>
                                <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Manual Assets %</label>
                                <x-text-input name="target_manual_pct" type="number" class="mt-1 block w-full"
                                              :value="old('target_manual_pct', $portfolio->target_manual_pct)"
                                              min="0" max="100" step="1" />
                            </div>
                        </div>
                        <x-input-error :messages="$errors->get('target_stock_pct')" class="mt-2" />
                    </div>

                    <div class="flex items-center gap-3">
                        <input id="is_tax_advantaged" name="is_tax_advantaged" type="checkbox" value="1"
                               class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm"
                               {{ old('is_tax_advantaged', $portfolio->is_tax_advantaged) ? 'checked' : '' }}>
                        <label for="is_tax_advantaged" class="text-sm text-gray-700 dark:text-gray-300">
                            Tax-advantaged account (401k, IRA, HSA, etc.) — excluded from tax summary
                        </label>
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>Save Changes</x-primary-button>
                        <a href="{{ route('portfolios.show', $portfolio) }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

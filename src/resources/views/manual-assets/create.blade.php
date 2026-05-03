<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                <a href="{{ route('portfolios.show', $portfolio) }}" class="hover:underline">{{ $portfolio->name }}</a>
            </p>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Add Manual Asset</h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('portfolios.manual-assets.store', $portfolio) }}" class="space-y-6">
                    @csrf

                    <div>
                        <x-input-label for="name" value="Asset Name" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                                      :value="old('name')" required autofocus maxlength="200"
                                      placeholder="123 Main Street" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="asset_class" value="Asset Class" />
                        <select id="asset_class" name="asset_class"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            @foreach ($assetClasses as $value => $label)
                                <option value="{{ $value }}" @selected(old('asset_class') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('asset_class')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="cost_basis" value="Cost Basis (optional)" />
                        <x-text-input id="cost_basis" name="cost_basis" type="number" class="mt-1 block w-48"
                                      :value="old('cost_basis')" min="0" step="any" placeholder="200000.00" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">What you paid. Used to calculate profit/loss vs current valuation.</p>
                        <x-input-error :messages="$errors->get('cost_basis')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="description" value="Description (optional)" />
                        <textarea id="description" name="description"
                                  class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                  rows="3" maxlength="1000">{{ old('description') }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="currency" value="Currency" />
                        <x-text-input id="currency" name="currency" type="text" class="mt-1 block w-32"
                                      :value="old('currency', $portfolio->currency)" required maxlength="3"
                                      placeholder="USD" style="text-transform:uppercase" />
                        <x-input-error :messages="$errors->get('currency')" class="mt-2" />
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>Add Asset</x-primary-button>
                        <a href="{{ route('portfolios.manual-assets.index', $portfolio) }}"
                           class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

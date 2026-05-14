<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                <a href="{{ route('liabilities.index') }}" class="hover:underline">Liabilities</a> &rsaquo;
                <a href="{{ route('liabilities.show', $liability) }}" class="hover:underline">{{ $liability->name }}</a>
            </p>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Edit Liability</h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('liabilities.update', $liability) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="name" value="Name" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                                      :value="old('name', $liability->name)" required autofocus maxlength="200" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="liability_type" value="Type" />
                        <select id="liability_type" name="liability_type"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            @foreach ($liabilityTypes as $value => $label)
                                <option value="{{ $value }}" @selected(old('liability_type', $liability->liability_type) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('liability_type')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="manual_asset_id" value="Secured by Asset (optional)" />
                        <select id="manual_asset_id" name="manual_asset_id"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">— None —</option>
                            @foreach ($manualAssets as $ma)
                                <option value="{{ $ma->id }}" @selected((string)old('manual_asset_id', $liability->manual_asset_id) === (string)$ma->id)>{{ $ma->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('manual_asset_id')" class="mt-2" />
                    </div>

                    <div class="flex flex-wrap gap-4">
                        <div>
                            <x-input-label for="interest_rate" value="Interest Rate % (optional)" />
                            <x-text-input id="interest_rate" name="interest_rate" type="number" class="mt-1 block w-32"
                                          :value="old('interest_rate', $liability->interest_rate)" min="0" max="100" step="0.001" />
                            <x-input-error :messages="$errors->get('interest_rate')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="minimum_payment" value="Minimum Payment (optional)" />
                            <x-text-input id="minimum_payment" name="minimum_payment" type="number" class="mt-1 block w-36"
                                          :value="old('minimum_payment', $liability->minimum_payment)" min="0" step="0.01"
                                          placeholder="25.00" />
                            <x-input-error :messages="$errors->get('minimum_payment')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="currency" value="Currency" />
                            <x-text-input id="currency" name="currency" type="text" class="mt-1 block w-32"
                                          :value="old('currency', $liability->currency)" required maxlength="3"
                                          style="text-transform:uppercase" />
                            <x-input-error :messages="$errors->get('currency')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="notes" value="Notes (optional)" />
                        <textarea id="notes" name="notes"
                                  class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                  rows="3" maxlength="1000">{{ old('notes', $liability->notes) }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>Save</x-primary-button>
                        <a href="{{ route('liabilities.show', $liability) }}"
                           class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

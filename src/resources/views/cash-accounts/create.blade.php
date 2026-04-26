<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                <a href="{{ route('cash-accounts.index') }}" class="hover:underline">Cash Accounts</a>
            </p>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Add Cash Account</h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('cash-accounts.store') }}" class="space-y-6">
                    @csrf

                    <div>
                        <x-input-label for="name" value="Name" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                                      :value="old('name')" required autofocus maxlength="200"
                                      placeholder="Chase Checking" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="account_type" value="Type" />
                        <select id="account_type" name="account_type"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            @foreach ($accountTypes as $value => $label)
                                <option value="{{ $value }}" @selected(old('account_type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('account_type')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="currency" value="Currency" />
                        <x-text-input id="currency" name="currency" type="text" class="mt-1 block w-32"
                                      :value="old('currency', 'USD')" required maxlength="3"
                                      placeholder="USD" style="text-transform:uppercase" />
                        <x-input-error :messages="$errors->get('currency')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="notes" value="Notes (optional)" />
                        <textarea id="notes" name="notes"
                                  class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                  rows="3" maxlength="1000">{{ old('notes') }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>Add Account</x-primary-button>
                        <a href="{{ route('cash-accounts.index') }}"
                           class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

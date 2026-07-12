<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                <a href="{{ route('cash-accounts.index') }}" class="hover:underline">Spending Accounts</a>
            </p>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Transfer Between Accounts</h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                    Records a linked withdrawal and deposit across two accounts — e.g. paying a credit card
                    bill from checking, or moving savings into a checking account. Neither leg is charged to an
                    envelope or income category, since no new spending or income occurred.
                </p>

                @if ($accounts->count() < 2)
                    <div class="rounded-md bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 px-3 py-2 text-sm text-amber-800 dark:text-amber-300">
                        You need at least two spending accounts to record a transfer.
                        <a href="{{ route('cash-accounts.create') }}" class="underline">Add another account</a>.
                    </div>
                @else
                    <form method="POST" action="{{ route('cash-transfers.store') }}" class="space-y-6">
                        @csrf

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="from_account_id" value="From Account" />
                                <select id="from_account_id" name="from_account_id" required
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">Select…</option>
                                    @foreach ($accounts as $a)
                                        <option value="{{ $a->id }}" @selected(old('from_account_id', request('from_account_id')) == $a->id)>{{ $demo->n($a->name) }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('from_account_id')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="to_account_id" value="To Account" />
                                <select id="to_account_id" name="to_account_id" required
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">Select…</option>
                                    @foreach ($accounts as $a)
                                        <option value="{{ $a->id }}" @selected(old('to_account_id', request('to_account_id')) == $a->id)>{{ $demo->n($a->name) }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('to_account_id')" class="mt-2" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="amount" value="Amount" />
                                <x-text-input id="amount" name="amount" type="number" class="mt-1 block w-full"
                                              :value="old('amount')" required min="0.01" step="any" placeholder="0.00" autofocus />
                                <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="occurred_at" value="Date" />
                                <x-text-input id="occurred_at" name="occurred_at" type="date" class="mt-1 block w-full"
                                              :value="old('occurred_at', now()->format('Y-m-d'))" required />
                                <x-input-error :messages="$errors->get('occurred_at')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="description" value="Description (optional)" />
                            <x-text-input id="description" name="description" type="text" class="mt-1 block w-full"
                                          :value="old('description')" maxlength="500" placeholder="Credit card payment" />
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>

                        <div>
                            <label class="inline-flex items-center gap-2 cursor-pointer select-none" title="Leave unchecked if this hasn't cleared the bank yet">
                                <input type="checkbox" name="cleared" value="1" {{ old('cleared') ? 'checked' : '' }}
                                       class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-indigo-600 focus:ring-indigo-500" />
                                <span class="text-sm text-gray-700 dark:text-gray-300">Cleared</span>
                            </label>
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>Record Transfer</x-primary-button>
                            <a href="{{ route('cash-accounts.all') }}"
                               class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Cancel</a>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

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
                    {{-- Either account can be the sender or the receiver; the page an account's
                         "Transfer" button came from only pre-fills one side (see
                         CashAccount::transferPrefillSide()). Swap flips the pair so a transfer
                         started from either account can be pointed either way without re-picking
                         both dropdowns. A transfer whose *destination* is a credit line is a card
                         payment, and the form says so — reversed, it's a cash advance, not a payment. --}}
                    @php
                        $fromSel = old('from_account_id', request('from_account_id'));
                        $toSel   = old('to_account_id', request('to_account_id'));
                    @endphp
                    <form method="POST" action="{{ route('cash-transfers.store') }}" class="space-y-6"
                          x-data="{
                              from: @js((string) $fromSel),
                              to: @js((string) $toSel),
                              cardIds: @js($accounts->filter->isCreditCard()->pluck('id')->values()),
                              get isPayment() { return this.cardIds.includes(Number(this.to)); },
                              swap() { [this.from, this.to] = [this.to, this.from]; },
                          }">
                        @csrf

                        <div class="flex flex-col sm:flex-row sm:items-start gap-3">
                            <div class="flex-1">
                                <x-input-label for="from_account_id" value="From Account" />
                                <select id="from_account_id" name="from_account_id" required x-model="from"
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">Select…</option>
                                    @foreach ($accounts as $a)
                                        <option value="{{ $a->id }}" @selected($fromSel == $a->id)>{{ $demo->n($a->name) }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('from_account_id')" class="mt-2" />
                            </div>

                            <button type="button" @click="swap()" title="Swap direction"
                                    aria-label="Swap the from and to accounts"
                                    class="shrink-0 self-center sm:self-start sm:mt-7 p-2 rounded-md border border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-700 dark:hover:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                                </svg>
                            </button>

                            <div class="flex-1">
                                <x-input-label for="to_account_id" value="To Account" />
                                <select id="to_account_id" name="to_account_id" required x-model="to"
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">Select…</option>
                                    @foreach ($accounts as $a)
                                        <option value="{{ $a->id }}" @selected($toSel == $a->id)>{{ $demo->n($a->name) }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('to_account_id')" class="mt-2" />
                            </div>
                        </div>

                        <p x-show="isPayment" x-cloak class="text-sm text-indigo-700 dark:text-indigo-300">
                            This lands on a credit line, so it's recorded as a card payment — it reduces what
                            you owe rather than adding income.
                        </p>

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
                                          :value="old('description')" maxlength="500" placeholder="Transfer"
                                          x-bind:placeholder="isPayment ? 'Credit card payment' : 'Transfer'" />
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
                            <x-primary-button><span x-text="isPayment ? 'Record Payment' : 'Record Transfer'">Record Transfer</span></x-primary-button>
                            <a href="{{ route('cash-accounts.all') }}"
                               class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Cancel</a>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

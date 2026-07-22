{{--
    "Record Transaction" add form.
    Params:
      $envelopes, $incomeCategories — for the conditional category pickers
      $accounts (Collection|null) — pass a Collection to show the account picker (cross-account
                 ledger); null when the account is fixed (single-account ledger). An empty
                 Collection hides the whole panel (nothing to record against).
      $account (CashAccount|null) — the fixed account, used only for the currency label
                 when $accounts is null.
    Shared by transaction-list.blade.php (single account) and all-transactions.blade.php (aggregate).
--}}
@if ($accounts === null || $accounts->isNotEmpty())
    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg mt-8">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Record Transaction</h3>
        </div>
        <div class="p-6 space-y-3">
            <form wire:submit="addTransaction"
                  x-data="{ type: @entangle('newType') }"
                  class="flex flex-wrap items-end gap-4">
                @csrf

                @if ($accounts !== null)
                    <div>
                        <x-input-label for="newAccountId" value="Account" />
                        <select id="newAccountId" wire:model="newAccountId"
                                class="mt-1 block w-44 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            @foreach ($accounts as $acct)
                                <option value="{{ $acct->id }}">{{ $demo->n($acct->name) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('newAccountId')" class="mt-2" />
                    </div>
                @endif

                <div>
                    <x-input-label for="newType" value="Type" />
                    <select id="newType" wire:model="newType" x-model="type"
                            class="mt-1 block w-36 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                        <option value="deposit">Deposit</option>
                        <option value="withdrawal">Withdrawal</option>
                    </select>
                    <x-input-error :messages="$errors->get('newType')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="newAmount" value="Amount{{ $account ? " ({$account->currency})" : '' }}" />
                    <x-text-input id="newAmount" wire:model="newAmount" type="number"
                                  class="mt-1 block {{ $accounts !== null ? 'w-32' : 'w-40' }}"
                                  required min="0" step="any" placeholder="0.00" />
                    <x-input-error :messages="$errors->get('newAmount')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="newOccurredAt" value="Date" />
                    <x-text-input id="newOccurredAt" wire:model="newOccurredAt" type="date" class="mt-1 block w-40" required />
                    <x-input-error :messages="$errors->get('newOccurredAt')" class="mt-2" />
                </div>

                <div class="flex-1 min-w-48">
                    <x-input-label for="newDescription" value="Description (optional)" />
                    <x-text-input id="newDescription" wire:model="newDescription" type="text" class="mt-1 block w-full"
                                  maxlength="500" placeholder="Paycheck, rent, groceries…" />
                    <x-input-error :messages="$errors->get('newDescription')" class="mt-2" />
                </div>

                @if ($envelopes->isNotEmpty())
                    <div x-show="type === 'withdrawal'" x-cloak>
                        <x-input-label for="newEnvelopeId" value="Charge to envelope (optional)" />
                        <select id="newEnvelopeId" wire:model="newEnvelopeId"
                                class="mt-1 block w-52 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">— None —</option>
                            @foreach ($envelopes as $env)
                                <option value="{{ $env->id }}">{{ $env->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('newEnvelopeId')" class="mt-2" />
                    </div>
                @endif

                <div x-show="type === 'deposit'" x-cloak>
                    <x-input-label for="newIncomeCategoryId" value="Income category (optional)" />
                    @if ($incomeCategories->isNotEmpty())
                        <select id="newIncomeCategoryId" wire:model="newIncomeCategoryId"
                                class="mt-1 block w-52 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="">— Uncategorized —</option>
                            @foreach ($incomeCategories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    @else
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 w-52">
                            <a href="{{ route('income-categories.create') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Add categories</a>
                            to label your income.
                        </p>
                    @endif
                    <x-input-error :messages="$errors->get('newIncomeCategoryId')" class="mt-2" />
                </div>

                <div class="pb-2">
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none" title="Leave unchecked for a pending transaction that hasn't cleared the bank yet">
                        <input type="checkbox" wire:model="newCleared"
                               class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-indigo-600 focus:ring-indigo-500" />
                        <span class="text-sm text-gray-700 dark:text-gray-300">Cleared</span>
                    </label>
                </div>

                <div class="pb-0.5">
                    <x-primary-button>Record</x-primary-button>
                </div>
            </form>
        </div>
    </div>
@endif

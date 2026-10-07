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
                              amount: @js((string) old('amount', '')),
                              accounts: @js($demo->scaleAmounts($summaries->toArray())),
                              get isPayment() { return !! this.accounts[this.to]?.isCard; },
                              swap() { [this.from, this.to] = [this.to, this.from]; },
                              get amt() { const n = parseFloat(this.amount); return n > 0 ? n : 0; },
                              acct(side) { return this.accounts[this[side]] ?? null; },
                              after(side) { return this.acct(side).balance + (side === 'from' ? -this.amt : this.amt); },
                              {{-- Cards store debt as a negative balance; show it as a positive amount owed. --}}
                              label(a, bal) {
                                  if (! a.isCard) return this.$money(bal);
                                  return bal <= 0.005 ? this.$money(-bal) + ' owed' : this.$money(bal) + ' credit';
                              },
                              tone(bal) { return bal < -0.005 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100'; },
                              get overdraws() {
                                  const a = this.acct('from');
                                  return a && ! a.isCard && this.amt > 0 && this.after('from') < -0.005;
                              },
                              fill(v) { this.amount = v.toFixed(2); },
                          }">
                        @csrf
                        <input type="hidden" name="return_account_id"
                               value="{{ old('return_account_id', request('to_account_id', request('from_account_id'))) }}">

                        <div class="flex flex-col sm:flex-row sm:items-start gap-3">
                            @foreach (['from' => ['From Account', $fromSel], 'to' => ['To Account', $toSel]] as $side => [$label, $sel])
                                @unless ($loop->first)
                                    <button type="button" @click="swap()" title="Swap direction"
                                            aria-label="Swap the from and to accounts"
                                            class="shrink-0 self-center sm:self-start sm:mt-7 p-2 rounded-md border border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-700 dark:hover:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                                        </svg>
                                    </button>
                                @endunless

                                <div class="flex-1">
                                    <x-input-label for="{{ $side }}_account_id" :value="$label" />
                                    <select id="{{ $side }}_account_id" name="{{ $side }}_account_id" required x-model="{{ $side }}"
                                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                        <option value="">Select…</option>
                                        @foreach ($accounts as $a)
                                            <option value="{{ $a->id }}" @selected($sel == $a->id)>{{ $demo->n($a->name) }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get($side.'_account_id')" class="mt-2" />
                                </div>
                            @endforeach
                        </div>

                        {{-- Balance + what's due for whichever accounts are picked, with a live
                             "after this transfer" figure as the amount is typed. --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" x-show="acct('from') || acct('to')" x-cloak>
                            <template x-for="side in ['from', 'to']" :key="side">
                                <div class="rounded-md border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 px-4 py-3 text-sm">
                                    <template x-if="! acct(side)">
                                        <p class="text-gray-400 dark:text-gray-500" x-text="side === 'from' ? 'Pick an account to send from.' : 'Pick an account to send to.'"></p>
                                    </template>
                                    <template x-if="acct(side)">
                                        <div class="space-y-2">
                                            <div class="flex items-baseline justify-between gap-2">
                                                <span class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Balance</span>
                                                <span class="font-mono font-semibold" :class="tone(acct(side).balance)"
                                                      x-text="label(acct(side), acct(side).balance)"></span>
                                            </div>
                                            <div class="flex items-baseline justify-between gap-2" x-show="amt > 0">
                                                <span class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">After transfer</span>
                                                <span class="font-mono font-semibold" :class="tone(after(side))"
                                                      x-text="label(acct(side), after(side))"></span>
                                            </div>

                                            <template x-if="acct(side).statement">
                                                <div class="pt-2 border-t border-gray-200 dark:border-gray-700 space-y-1">
                                                    <div class="flex items-baseline justify-between gap-2">
                                                        <span class="text-gray-600 dark:text-gray-400">Statement <span class="text-xs" x-text="'(closed ' + acct(side).statement.closedOn + ')'"></span></span>
                                                        <span class="font-mono" x-text="$money(acct(side).statement.balance)"></span>
                                                    </div>
                                                    <div class="flex items-baseline justify-between gap-2" x-show="acct(side).statement.paidSince > 0">
                                                        <span class="text-gray-600 dark:text-gray-400">Paid since</span>
                                                        <span class="font-mono text-green-600 dark:text-green-400" x-text="$money(acct(side).statement.paidSince)"></span>
                                                    </div>
                                                    <div class="flex items-baseline justify-between gap-2">
                                                        <span class="font-medium text-gray-800 dark:text-gray-200">Still due</span>
                                                        <span class="font-mono font-semibold"
                                                              :class="acct(side).statement.remaining > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'"
                                                              x-text="acct(side).statement.remaining > 0 ? $money(acct(side).statement.remaining) : 'Paid ✓'"></span>
                                                    </div>
                                                </div>
                                            </template>

                                            {{-- One-click amounts when paying a card. --}}
                                            <div class="flex flex-wrap gap-2" x-show="side === 'to' && acct(side).isCard && ! window.__demoMode">
                                                <button type="button" x-show="acct(side).statement?.remaining > 0"
                                                        @click="fill(acct(side).statement.remaining)"
                                                        class="px-2 py-1 rounded border border-indigo-300 dark:border-indigo-700 text-xs text-indigo-700 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30">
                                                    Pay statement
                                                </button>
                                                <button type="button" x-show="acct(side).balance < -0.005"
                                                        @click="fill(-acct(side).balance)"
                                                        class="px-2 py-1 rounded border border-indigo-300 dark:border-indigo-700 text-xs text-indigo-700 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30">
                                                    Pay full balance
                                                </button>
                                            </div>

                                            <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                                                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Scheduled · next {{ $upcomingDays }} days</p>
                                                <template x-if="! acct(side).upcoming.length">
                                                    <p class="text-xs text-gray-400 dark:text-gray-500">Nothing scheduled.</p>
                                                </template>
                                                <ul class="space-y-0.5">
                                                    <template x-for="(u, i) in acct(side).upcoming" :key="i">
                                                        <li class="flex items-baseline justify-between gap-2 text-xs">
                                                            <span class="truncate text-gray-700 dark:text-gray-300">
                                                                <span class="text-gray-400 dark:text-gray-500" x-text="u.due"></span>
                                                                <span x-text="u.description"></span>
                                                            </span>
                                                            <span class="font-mono shrink-0" :class="u.amount < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'"
                                                                  x-text="(u.amount > 0 ? '+' : '') + $money(u.amount)"></span>
                                                        </li>
                                                    </template>
                                                </ul>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>

                        <p x-show="overdraws" x-cloak class="text-sm text-amber-700 dark:text-amber-300">
                            This would take the sending account below zero.
                        </p>

                        <p x-show="isPayment" x-cloak class="text-sm text-indigo-700 dark:text-indigo-300">
                            This lands on a credit line, so it's recorded as a card payment — it reduces what
                            you owe rather than adding income.
                        </p>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="amount" value="Amount" />
                                <x-text-input id="amount" name="amount" type="number" class="mt-1 block w-full"
                                              x-model="amount" required min="0.01" step="any" placeholder="0.00" autofocus />
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

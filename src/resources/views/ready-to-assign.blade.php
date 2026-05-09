<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">Money</p>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Ready to Assign</h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg px-5 py-3 text-sm text-green-800 dark:text-green-300">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('info'))
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg px-5 py-3 text-sm text-blue-800 dark:text-blue-300">
                    {{ session('info') }}
                </div>
            @endif

            {{-- RTA Banner --}}
            <div class="sm:rounded-lg px-6 py-5 text-center
                @if ($readyToAssign > 0) bg-green-600 dark:bg-green-700
                @elseif ($readyToAssign < 0) bg-red-600 dark:bg-red-700
                @else bg-gray-500 dark:bg-gray-600
                @endif">
                <p class="text-sm text-white/80 uppercase tracking-wide font-medium">Ready to Assign</p>
                <p class="mt-1 text-4xl font-bold font-mono text-white">
                    @if ($readyToAssign < 0)−@endif${{ number_format(abs($readyToAssign), 2) }}
                </p>
                @if ($readyToAssign < 0)
                    <p class="mt-2 text-sm text-white/80">You've assigned more than you've recorded as income. Add an income entry to reconcile.</p>
                @elseif ($readyToAssign == 0)
                    <p class="mt-2 text-sm text-white/80">All money assigned — your budget is balanced.</p>
                @else
                    <p class="mt-2 text-sm text-white/80">Assign this to your envelopes below.</p>
                @endif
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

                {{-- Left: Income log --}}
                <div class="lg:col-span-2 space-y-4">

                    {{-- Add income form --}}
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-5">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">Record Income</h3>
                        <form method="POST" action="{{ route('income-entries.store') }}" class="space-y-3">
                            @csrf
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Amount</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-500 dark:text-gray-400 text-sm">$</span>
                                    <input type="number" name="amount" step="0.01" min="0.01" required
                                           class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm pl-7"
                                           placeholder="0.00"
                                           value="{{ old('amount') }}">
                                </div>
                                @error('amount') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                                <input type="text" name="description" maxlength="500"
                                       class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                       placeholder="Paycheck, bonus, transfer…"
                                       value="{{ old('description') }}">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Date</label>
                                <input type="date" name="occurred_at" required
                                       class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                       value="{{ old('occurred_at', now()->toDateString()) }}">
                                @error('occurred_at') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>
                            <button type="submit"
                                    class="w-full inline-flex justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                Add Income
                            </button>
                        </form>
                    </div>

                    {{-- Income history --}}
                    @if ($recentIncome->isNotEmpty())
                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Recent Income</h3>
                            </div>
                            <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($recentIncome as $entry)
                                    <li class="px-6 py-3 flex items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-sm text-gray-800 dark:text-gray-200 truncate">
                                                {{ $entry->description ?: '—' }}
                                            </p>
                                            <p class="text-xs text-gray-400 dark:text-gray-500">{{ $entry->occurred_at->format('M j, Y') }}</p>
                                        </div>
                                        <div class="flex items-center gap-3 shrink-0">
                                            <span class="text-sm font-mono text-green-700 dark:text-green-400">+${{ number_format($entry->amount, 2) }}</span>
                                            <form method="POST" action="{{ route('income-entries.destroy', $entry) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="text-gray-400 dark:text-gray-500 hover:text-red-500 dark:hover:text-red-400 transition"
                                                        onclick="return confirm('Remove this income entry?')"
                                                        title="Remove">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                {{-- Right: Assign to envelopes --}}
                <div class="lg:col-span-3">
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Assign to Envelopes</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Enter amounts to fund envelopes from your ready-to-assign balance.</p>
                        </div>

                        @if ($envelopes->isEmpty())
                            <div class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                <p>No envelopes yet.</p>
                                <a href="{{ route('envelopes.create') }}" class="mt-2 inline-block text-indigo-600 dark:text-indigo-400 hover:underline">Create your first envelope &rarr;</a>
                            </div>
                        @else
                            <form method="POST" action="{{ route('ready-to-assign.assign') }}" x-data="assignForm()" @submit="submitForm">
                                @csrf
                                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach ($envelopes as $envelope)
                                        <div class="px-6 py-3 flex items-center gap-3">
                                            <span class="inline-block w-2.5 h-2.5 rounded-full shrink-0"
                                                  style="background-color: {{ $envelope->color }}"></span>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm text-gray-800 dark:text-gray-200 truncate">{{ $envelope->name }}</p>
                                                <p class="text-xs text-gray-400 dark:text-gray-500 font-mono">
                                                    Balance: ${{ number_format($envelope->current_balance, 2) }}
                                                    @if ($envelope->monthly_target)
                                                        · Target: ${{ number_format($envelope->monthly_target, 2) }}/mo
                                                    @endif
                                                </p>
                                            </div>
                                            <div class="relative w-32 shrink-0">
                                                <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 text-sm pointer-events-none">$</span>
                                                <input type="number"
                                                       name="amounts[{{ $envelope->id }}]"
                                                       step="0.01"
                                                       min="0"
                                                       x-model.number="amounts[{{ $envelope->id }}]"
                                                       class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm pl-7 py-1.5"
                                                       placeholder="0.00">
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/40 flex items-center justify-between gap-4">
                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                        Assigning:
                                        <span class="font-mono font-semibold text-gray-800 dark:text-gray-200"
                                              x-text="'$' + total.toFixed(2)">$0.00</span>
                                        <span class="ml-2 text-xs">
                                            · Remaining:
                                            <span class="font-mono" :class="remaining < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-700 dark:text-gray-300'"
                                                  x-text="(remaining < 0 ? '−' : '') + '$' + Math.abs(remaining).toFixed(2)">
                                                ${{ number_format($readyToAssign, 2) }}
                                            </span>
                                        </span>
                                    </div>
                                    <button type="submit"
                                            class="inline-flex justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        Assign
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function assignForm() {
            return {
                amounts: {},
                rta: {{ $readyToAssign }},
                get total() {
                    return Object.values(this.amounts).reduce((s, v) => s + (parseFloat(v) || 0), 0);
                },
                get remaining() {
                    return this.rta - this.total;
                },
            };
        }
    </script>
</x-app-layout>

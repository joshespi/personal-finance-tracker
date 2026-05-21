<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Budget Envelopes</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Allocate money into envelopes; spend from them as the month goes.</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-1">
                    <a href="{{ route('envelopes.index', ['month' => $prevMonth]) }}"
                       class="p-1.5 rounded-md text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300 min-w-[96px] text-center">
                        {{ $month->format('M Y') }}
                    </span>
                    @if ($isCurrentMonth)
                        <span class="p-1.5 rounded-md text-gray-300 dark:text-gray-600 cursor-not-allowed">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </span>
                    @else
                        <a href="{{ route('envelopes.index', ['month' => $nextMonth]) }}"
                           class="p-1.5 rounded-md text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    @endif
                </div>
                <a href="{{ route('envelopes.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-gray-600 transition">
                    + Add Envelope
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="bg-green-100 dark:bg-green-900/40 border border-green-300 dark:border-green-700 text-green-800 dark:text-green-300 rounded-md px-4 py-3 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if ($envelopes->isNotEmpty())
                {{-- RTA Banner --}}
                <div class="sm:rounded-lg px-6 py-4 flex items-center justify-between gap-6
                    @if ($readyToAssign > 0) bg-green-600 dark:bg-green-700
                    @elseif ($readyToAssign < 0) bg-red-600 dark:bg-red-700
                    @else bg-gray-500 dark:bg-gray-600
                    @endif"
                    x-data="{ rta: {{ round($demo->scaleScalar($readyToAssign), 2) }} }"
                    @rta-updated.window="rta = $event.detail">
                    <div>
                        <p class="text-xs text-white/70 uppercase tracking-wide font-medium">Ready to Assign</p>
                        <p class="mt-0.5 text-3xl font-bold font-mono text-white"
                           x-text="(rta < 0 ? '−' : '') + '$' + Math.abs(rta).toFixed(2)">
                            @if ($readyToAssign < 0)−@endif${{ $demo->amt(abs($readyToAssign)) }}
                        </p>
                    </div>
                    @if ($readyToAssign < 0)
                        <p class="text-sm text-white/80 max-w-sm">Your envelope balances exceed your cash. Deposit money or reduce envelope funding to reconcile.</p>
                    @elseif ($readyToAssign == 0)
                        <p class="text-sm text-white/80">All money assigned.</p>
                    @else
                        <p class="text-sm text-white/80">Assign this to your envelopes below.</p>
                    @endif
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total in Envelopes</p>
                        <p class="mt-1 text-2xl font-semibold font-mono text-gray-900 dark:text-gray-100">${{ $demo->amt($totalBalance) }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Funded in {{ $month->format('M Y') }}</p>
                        <p class="mt-1 text-2xl font-semibold font-mono text-indigo-600 dark:text-indigo-400">+${{ $demo->amt($totalFundedMonth) }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Spent in {{ $month->format('M Y') }}</p>
                        <p class="mt-1 text-2xl font-semibold font-mono text-red-600 dark:text-red-400">−${{ $demo->amt($totalSpentMonth) }}</p>
                    </div>
                </div>
            @endif

            @if ($envelopes->isEmpty())
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="p-6 text-sm text-gray-500 dark:text-gray-400 text-center">No envelopes yet. Create one for each spending category (Groceries, Rent, Fun Money…).</div>
                </div>
            @else
                @php
                    $groupDescriptions = [
                        'Emergency Fund'  => 'Your safety net — covers mandatory expenses if income stops.',
                        'Mandatory'       => 'Fixed costs that must be paid every month (rent, utilities, insurance).',
                        'Wealth Building' => 'Retirement contributions and long-term investing — grows your net worth.',
                        'Spending'        => 'Everything else, including sinking funds for planned purchases.',
                    ];
                @endphp
                <div x-data="assignForm()">
                    @foreach ($groups as $groupName => $groupEnvelopes)
                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden mb-4">
                            <div class="px-6 py-2.5 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                                <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide"
                                    title="{{ $groupDescriptions[$groupName] ?? '' }}">{{ $groupName }}</h3>
                            </div>
                            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($groupEnvelopes as $e)
                                    @php
                                        $target     = (float) ($e->monthly_target ?? 0);
                                        $goalAmount = (float) ($e->goal_amount ?? 0);
                                        $spent      = (float) $e->spent_this_month;
                                        $funded     = (float) $e->funded_this_month;
                                        $pct        = $target > 0 ? min(100, round($spent / $target * 100)) : 0;
                                        $overBudget = $target > 0 && $spent > $target;
                                        $goalPct    = $goalAmount > 0 ? min(100, round($e->current_balance / $goalAmount * 100)) : 0;

                                        $parts = [];
                                        if ($funded > 0) $parts[] = 'funded $' . $demo->amt($funded);
                                        if ($target > 0) {
                                            $parts[] = 'spent $' . $demo->amt($spent) . ' / $' . $demo->amt($target);
                                        } elseif ($spent > 0) {
                                            $parts[] = 'spent $' . $demo->amt($spent);
                                        }
                                        if ($goalAmount > 0) {
                                            $goalLabel = 'goal $' . $demo->amt($e->current_balance) . ' / $' . $demo->amt($goalAmount);
                                            if ($e->goal_date) $goalLabel .= ' by ' . $e->goal_date->format('M Y');
                                            $parts[] = $goalLabel;
                                        }
                                    @endphp
                                    <div class="px-6 py-4">
                                        <div class="flex items-center justify-between gap-4">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <span class="inline-block w-3 h-3 rounded-full shrink-0" style="background-color: {{ $e->color }}"></span>
                                                <a href="{{ route('envelopes.show', $e) }}"
                                                   class="font-medium text-gray-900 dark:text-gray-100 hover:underline truncate">{{ $demo->n($e->name) }}</a>
                                            </div>
                                            <div class="flex items-center gap-4">
                                                {{-- Balance + sub-labels --}}
                                                <div class="text-right min-w-[90px]">
                                                    <p class="font-mono {{ $e->current_balance >= 0 ? 'text-gray-900 dark:text-gray-100' : 'text-red-600' }} text-sm"
                                                       x-text="'$' + (balances[{{ $e->id }}] ?? {{ (float) $e->current_balance }}).toFixed(2)">
                                                        {{ $e->current_balance < 0 ? '−' : '' }}${{ $demo->amt(abs($e->current_balance)) }}
                                                    </p>
                                                    @if (count($parts))
                                                        <p class="text-xs {{ $overBudget ? 'text-red-500' : 'text-gray-400 dark:text-gray-500' }}">
                                                            {{ implode(' · ', $parts) }}
                                                        </p>
                                                    @endif
                                                </div>
                                                {{-- Funding input --}}
                                                @if ($isCurrentMonth)
                                                    <div class="relative w-28 shrink-0">
                                                        <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 text-sm pointer-events-none">$</span>
                                                        <input type="number"
                                                               step="0.01" min="0"
                                                               x-model="inputs[{{ $e->id }}]"
                                                               class="rta-input block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm pl-7 py-1.5"
                                                               :class="saved[{{ $e->id }}] ? 'border-green-400 dark:border-green-500' : ''"
                                                               @focus="onFocus({{ $e->id }}, $el)"
                                                               @blur="onBlur({{ $e->id }}, $el)"
                                                               @keydown.arrow-down.prevent="navigate($el, 1)"
                                                               @keydown.arrow-up.prevent="navigate($el, -1)"
                                                               @keydown.enter.prevent="onBlur({{ $e->id }}, $el)"
                                                               @keydown.tab="onBlur({{ $e->id }}, $el)">
                                                    </div>
                                                @endif
                                                {{-- Actions --}}
                                                <div class="flex items-center gap-2">
                                                    <a href="{{ route('envelopes.show', $e) }}"
                                                       class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">View</a>
                                                    <a href="{{ route('envelopes.edit', $e) }}"
                                                       class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">Edit</a>
                                                    <form method="POST" action="{{ route('envelopes.destroy', $e) }}" class="inline"
                                                          onsubmit="return confirm('Delete this envelope and all its transactions?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-red-600 border border-transparent rounded-md text-xs font-semibold text-white hover:bg-red-500 transition">Delete</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        @if ($target > 0)
                                            <div class="mt-3 w-full bg-gray-100 dark:bg-gray-700 rounded-full h-1.5 overflow-hidden">
                                                <div class="h-full rounded-full transition-all"
                                                     style="width: {{ $pct }}%; background-color: {{ $overBudget ? '#dc2626' : $e->color }};"></div>
                                            </div>
                                        @endif
                                        @if ($goalAmount > 0)
                                            <div class="mt-1.5 w-full bg-gray-100 dark:bg-gray-700 rounded-full h-1.5 overflow-hidden">
                                                <div class="h-full rounded-full transition-all"
                                                     style="width: {{ $goalPct }}%; background-color: {{ $e->color }}; opacity: 0.6;"></div>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div x-show="error" x-cloak class="text-xs text-red-500 px-1" x-text="error"></div>
                </div>
            @endif
        </div>
    </div>

    @if ($isCurrentMonth && $envelopes->isNotEmpty())
    <script>
        function assignForm() {
            return {
                inputs:      @json($groups->flatten()->mapWithKeys(fn($e) => [$e->id => (float)$e->monthly_target ?: (float)$e->current_balance])),
                balances:    {},
                focusValues: {},
                saved:       {},
                error:       '',

                onFocus(envelopeId, el) {
                    this.focusValues[envelopeId] = this.inputs[envelopeId];
                    el.select();
                },

                async onBlur(envelopeId, el) {
                    const current  = parseFloat(this.inputs[envelopeId]) || 0;
                    const original = parseFloat(this.focusValues[envelopeId]) || 0;
                    if (current === original || current <= 0) return;

                    this.error = '';
                    try {
                        const res = await fetch('{{ route('envelopes.assign-one') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ envelope_id: envelopeId, amount: current }),
                        });
                        if (!res.ok) throw new Error();
                        const data = await res.json();

                        window.dispatchEvent(new CustomEvent('rta-updated', { detail: data.ready_to_assign }));

                        this.balances[envelopeId]    = data.envelope_balance;
                        this.focusValues[envelopeId] = current;
                        this.saved[envelopeId]       = true;
                        setTimeout(() => { this.saved[envelopeId] = false; }, 1500);
                    } catch {
                        this.error = 'Failed to save — check your connection.';
                    }
                },

                navigate(el, dir) {
                    const inputs = Array.from(document.querySelectorAll('.rta-input'));
                    const idx    = inputs.indexOf(el);
                    const next   = inputs[idx + dir];
                    if (next) { next.focus(); next.select(); }
                },
            };
        }
    </script>
    @endif
</x-app-layout>

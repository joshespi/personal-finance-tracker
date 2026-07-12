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
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </a>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300 min-w-[96px] text-center">{{ $month->format('M Y') }}</span>
                    @if ($isCurrentMonth)
                        <span class="p-1.5 rounded-md text-gray-300 dark:text-gray-600 cursor-not-allowed">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </span>
                    @else
                        <a href="{{ route('envelopes.index', ['month' => $nextMonth]) }}"
                           class="p-1.5 rounded-md text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
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
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-5">

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
                        <p class="text-sm text-white/80 max-w-sm">Envelope balances exceed your cash. Deposit money or reduce envelope funding to reconcile.</p>
                    @elseif ($readyToAssign == 0)
                        <p class="text-sm text-white/80">All money assigned.</p>
                    @else
                        <p class="text-sm text-white/80">Assign this to your envelopes below.</p>
                    @endif
                </div>

                {{-- Summary stats --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <x-stat-tile>
                        <x-slot:label>Left over from {{ $month->copy()->subMonth()->format('M') }}</x-slot:label>
                        <p class="mt-1 text-xl font-semibold font-mono {{ $leftOverFromLastMonth >= 0 ? 'text-gray-900 dark:text-gray-100' : 'text-red-600 dark:text-red-400' }}">
                            {{ $leftOverFromLastMonth < 0 ? '−' : '' }}${{ $demo->amt(abs($leftOverFromLastMonth)) }}
                        </p>
                    </x-stat-tile>
                    <x-stat-tile>
                        <x-slot:label>Assigned in {{ $month->format('M') }}</x-slot:label>
                        <p class="mt-1 text-xl font-semibold font-mono text-indigo-600 dark:text-indigo-400">+${{ $demo->amt($totalFundedMonth) }}</p>
                    </x-stat-tile>
                    <x-stat-tile>
                        <x-slot:label>Activity in {{ $month->format('M') }}</x-slot:label>
                        <p class="mt-1 text-xl font-semibold font-mono text-red-600 dark:text-red-400">−${{ $demo->amt($totalSpentMonth) }}</p>
                    </x-stat-tile>
                    <x-stat-tile>
                        <x-slot:label>Total available</x-slot:label>
                        <p class="mt-1 text-xl font-semibold font-mono {{ $totalBalance >= 0 ? 'text-gray-900 dark:text-gray-100' : 'text-red-600 dark:text-red-400' }}">${{ $demo->amt($totalBalance) }}</p>
                    </x-stat-tile>
                </div>

            @endif

            @if ($envelopes->isEmpty())
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-sm text-gray-500 dark:text-gray-400 text-center">
                    No envelopes yet. Create one for each spending category (Groceries, Rent, Fun Money…).
                </div>
            @else

                <div x-data="budgetTable()" x-cloak>

                    {{-- Filter chips --}}
                    <div class="flex flex-wrap gap-2 pb-3">
                        @foreach (['all' => 'All', 'underfunded' => 'Underfunded', 'overfunded' => 'Overfunded', 'available' => 'Money Available', 'spent' => 'Fully Spent'] as $key => $label)
                            <button type="button"
                                    @click="activeFilter = '{{ $key }}'"
                                    :class="activeFilter === '{{ $key }}'
                                        ? 'bg-indigo-600 text-white border-indigo-600'
                                        : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:border-indigo-400'"
                                    class="px-3 py-1 text-xs font-medium rounded-full border transition">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>

                    {{-- Column headers (sm+) --}}
                    <div class="hidden sm:grid envelope-row-grid px-4 pb-1.5 text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide">
                        <div>Category</div>
                        <div class="text-right">Assigned</div>
                        <div class="text-right">Activity</div>
                        <div class="text-right pr-1">Available</div>
                        <div></div>
                    </div>

                    {{-- Groups --}}
                    @foreach ($groups as $groupName => $groupEnvelopes)
                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden mb-3"
                             x-show="groupVisible('{{ $groupName }}')">

                            {{-- Group header --}}
                            <div class="envelope-row-grid items-center px-4 py-2.5 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/60 cursor-pointer select-none"
                                 @click="collapsed['{{ $groupName }}'] = !collapsed['{{ $groupName }}']">
                                <div class="flex items-center gap-2 min-w-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 shrink-0 text-gray-400 transition-transform"
                                         :class="collapsed['{{ $groupName }}'] ? '-rotate-90' : ''"
                                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                    <span class="text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wide truncate">{{ $groupName }}</span>
                                </div>
                                @php $gt = $groupTotals[$groupName] @endphp
                                <div class="hidden sm:block text-right text-xs font-mono text-gray-600 dark:text-gray-400">
                                    @if ($gt['assigned'] > 0)+@endif${{ $demo->amt($gt['assigned']) }}
                                </div>
                                <div class="hidden sm:block text-right text-xs font-mono {{ $gt['activity'] > 0 ? 'text-red-500 dark:text-red-400' : 'text-gray-400 dark:text-gray-500' }}">
                                    {{ $gt['activity'] > 0 ? '−$' . $demo->amt($gt['activity']) : '$0.00' }}
                                </div>
                                <div class="text-right text-xs font-mono font-semibold {{ $gt['available'] > 0 ? 'text-gray-700 dark:text-gray-300' : ($gt['available'] < 0 ? 'text-red-600' : 'text-gray-400 dark:text-gray-500') }}">
                                    {{ $gt['available'] < 0 ? '−' : '' }}${{ $demo->amt(abs($gt['available'])) }}
                                </div>
                                <div></div>
                            </div>

                            {{-- Envelope rows --}}
                            <div x-show="!collapsed['{{ $groupName }}']">
                                @foreach ($groupEnvelopes as $e)
                                    @php
                                        $target  = (float) ($e->monthly_target ?? 0);
                                        $spent   = (float) $e->spent_this_month;
                                        $funded  = (float) $e->funded_this_month;
                                        $balance = (float) $e->current_balance;
                                        $pct     = $target > 0 ? min(100, round($spent / $target * 100)) : 0;
                                        $over    = $target > 0 && $spent > $target;
                                        $goalAmt = (float) ($e->goal_amount ?? 0);
                                        $goalPct = $goalAmt > 0 ? min(100, round($balance / $goalAmt * 100)) : 0;
                                    @endphp
                                    <div class="border-b border-gray-50 dark:border-gray-700/50 last:border-0 group/row"
                                         x-show="rowVisible($el)"
                                         data-env-id="{{ $e->id }}"
                                         data-balance="{{ $balance }}"
                                         data-target="{{ $target }}"
                                         data-funded="{{ $funded }}"
                                         data-spent="{{ $spent }}">

                                        <div class="envelope-row-grid items-center px-4 py-3 gap-x-3">

                                            {{-- Category name --}}
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <span class="inline-block w-2.5 h-2.5 rounded-full shrink-0" style="background-color: {{ $e->color }}"></span>
                                                <a href="{{ route('envelopes.show', $e) }}"
                                                   class="text-sm font-medium text-gray-900 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400 truncate transition">
                                                    {{ $demo->n($e->name) }}
                                                </a>
                                                @if ($goalAmt > 0 && $e->goal_date)
                                                    <span class="hidden sm:inline text-xs text-gray-400 dark:text-gray-500 shrink-0">
                                                        goal by {{ $e->goal_date->format('M Y') }}
                                                    </span>
                                                @endif
                                            </div>

                                            {{-- Assigned (input editable for current + past months; read-only for future) --}}
                                            <div class="hidden sm:block text-right">
                                                @if (! $isFutureMonth)
                                                    <div class="relative inline-flex items-center">
                                                        <span class="absolute left-2.5 text-gray-400 text-sm pointer-events-none">$</span>
                                                        <input type="number" step="0.01" min="0"
                                                               x-model="inputs[{{ $e->id }}]"
                                                               class="rta-input w-32 rounded-md border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm text-right pl-6 pr-2 py-1"
                                                               :class="saved[{{ $e->id }}] ? 'border-green-400 dark:border-green-500 bg-green-50 dark:bg-green-900/20' : ''"
                                                               @focus="onFocus({{ $e->id }}, $el)"
                                                               @blur="onBlur({{ $e->id }}, $el)"
                                                               @keydown.arrow-down.prevent="navigate($el, 1)"
                                                               @keydown.arrow-up.prevent="navigate($el, -1)"
                                                               @keydown.enter.prevent="onBlur({{ $e->id }}, $el)">
                                                    </div>
                                                @else
                                                    <span class="text-sm font-mono text-gray-500 dark:text-gray-400">
                                                        {{ ($funded > 0 ? '+' : '') . '$' . $demo->amt($funded) }}
                                                    </span>
                                                @endif
                                            </div>

                                            {{-- Activity --}}
                                            <div class="hidden sm:block text-right">
                                                <span class="text-sm font-mono {{ $spent > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400 dark:text-gray-500' }}">
                                                    {{ $spent > 0 ? '−$' . $demo->amt($spent) : '$0.00' }}
                                                </span>
                                            </div>

                                            {{-- Available pill --}}
                                            <div class="text-right">
                                                <span class="inline-flex items-center justify-end min-w-[72px] px-2.5 py-0.5 rounded-full text-xs font-mono font-semibold transition"
                                                      :class="pillClass(balances[{{ $e->id }}] ?? {{ $balance }})"
                                                      x-text="formatMoney(balances[{{ $e->id }}] ?? {{ $balance }})">
                                                    {{ $balance < 0 ? '−' : '' }}${{ $demo->amt(abs($balance)) }}
                                                </span>
                                            </div>

                                            {{-- Actions (hover) --}}
                                            <div class="hidden sm:flex items-center justify-end gap-1 opacity-0 group-hover/row:opacity-100 transition-opacity">
                                                <a href="{{ route('envelopes.edit', $e) }}"
                                                   class="p-1 rounded text-gray-400 dark:text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                                   title="Edit">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                    </svg>
                                                </a>
                                                <form method="POST" action="{{ route('envelopes.destroy', $e) }}" class="inline"
                                                      onsubmit="return confirm('Delete this envelope and all its transactions?')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit"
                                                            class="p-1 rounded text-gray-400 dark:text-gray-500 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition"
                                                            title="Delete">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>

                                        </div>

                                        {{-- Progress bars --}}
                                        @if ($target > 0 || $goalAmt > 0)
                                            <div class="px-4 pb-2.5 space-y-1">
                                                @if ($target > 0)
                                                    <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-1 overflow-hidden">
                                                        <div class="h-full rounded-full transition-all"
                                                             style="width: {{ $pct }}%; background-color: {{ $over ? '#dc2626' : $e->color }};"></div>
                                                    </div>
                                                @endif
                                                @if ($goalAmt > 0)
                                                    <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-1 overflow-hidden">
                                                        <div class="h-full rounded-full transition-all"
                                                             style="width: {{ $goalPct }}%; background-color: {{ $e->color }}; opacity: 0.5;"></div>
                                                    </div>
                                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                                        goal ${{ $demo->amt($balance) }} / ${{ $demo->amt($goalAmt) }}@if ($e->goal_date) by {{ $e->goal_date->format('M Y') }}@endif
                                                    </p>
                                                @endif
                                            </div>
                                        @endif

                                    </div>
                                @endforeach
                            </div>

                        </div>
                    @endforeach

                    <div x-show="error" class="text-xs text-red-500 px-1 mt-1" x-text="error"></div>

                </div>

            @endif
        </div>
    </div>

    @verbatim
    <style>
        .envelope-row-grid {
            display: grid;
            grid-template-columns: 1fr auto auto auto auto;
            gap: 0 0.75rem;
            align-items: center;
        }
        @media (min-width: 640px) {
            .envelope-row-grid {
                grid-template-columns: 1fr 9rem 7rem 7rem 3rem;
            }
        }
        @media (max-width: 639px) {
            .envelope-row-grid > *:nth-child(2),
            .envelope-row-grid > *:nth-child(3),
            .envelope-row-grid > *:nth-child(5) {
                display: none;
            }
        }
    </style>
    @endverbatim

    @php
        $jsInputs    = $groups->flatten()->mapWithKeys(fn ($e) => [$e->id => round((float) $e->funded_this_month, 2)]);
        $jsGroupData = $groups->map(fn ($g) => $g->map(fn ($e) => [
            'id'      => $e->id,
            'balance' => (float) $e->current_balance,
            'target'  => (float) ($e->monthly_target ?? 0),
            'funded'  => (float) $e->funded_this_month,
            'spent'   => (float) $e->spent_this_month,
        ])->values());
    @endphp

    <script>
    function budgetTable() {
        return {
            collapsed:    {},
            activeFilter: 'all',
            month:        '{{ $month->format('Y-m') }}',
            inputs:       @json($jsInputs),
            balances:     {},
            focusValues:  {},
            saved:        {},
            error:        '',

            groupData: @json($jsGroupData),

            groupVisible(name) {
                if (this.activeFilter === 'all') return true;
                const envs = this.groupData[name] || [];
                return envs.some(e => this.envMatchesFilter(e));
            },

            envMatchesFilter(e) {
                const balance = this.balances[e.id] ?? e.balance;
                switch (this.activeFilter) {
                    case 'underfunded': return e.target > 0 && e.funded < e.target;
                    case 'overfunded':  return e.target > 0 && balance > e.target;
                    case 'available':   return balance > 0.005;
                    case 'spent':       return e.target > 0 && e.spent >= e.target;
                }
                return true;
            },

            rowVisible(el) {
                if (this.activeFilter === 'all') return true;
                const e = {
                    id:      parseInt(el.dataset.envId),
                    balance: parseFloat(el.dataset.balance),
                    target:  parseFloat(el.dataset.target),
                    funded:  parseFloat(el.dataset.funded),
                    spent:   parseFloat(el.dataset.spent),
                };
                return this.envMatchesFilter(e);
            },

            pillClass(val) {
                if (val > 0.005)  return 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300';
                if (val < -0.005) return 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300';
                return 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400';
            },

            formatMoney(val) {
                return (val < -0.005 ? '−' : '') + '$' + Math.abs(val).toFixed(2);
            },

            onFocus(id, el) {
                this.focusValues[id] = this.inputs[id];
                el.select();
            },

            async onBlur(id, el) {
                const current  = parseFloat(this.inputs[id]); // blank blur parses to NaN → no-op
                const original = parseFloat(this.focusValues[id]) || 0;
                if (isNaN(current) || current < 0 || current === original) return;

                // Commit optimistically so an Enter-then-blur can't double-submit the same edit.
                this.focusValues[id] = current;
                this.error = '';
                try {
                    const res = await fetch('{{ route('envelopes.assign-one') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ envelope_id: id, amount: current, month: this.month }),
                    });
                    if (!res.ok) throw new Error();
                    const data = await res.json();
                    window.dispatchEvent(new CustomEvent('rta-updated', { detail: data.ready_to_assign }));
                    this.balances[id]    = data.envelope_balance;
                    this.focusValues[id] = current;
                    this.saved[id]       = true;
                    setTimeout(() => { this.saved[id] = false; }, 1500);
                } catch {
                    this.focusValues[id] = original; // roll back so a retry re-fires
                    this.error = 'Failed to save — check your connection.';
                }
            },

            navigate(el, dir) {
                const inputs = Array.from(document.querySelectorAll('.rta-input')).filter(i => i.offsetParent !== null);
                const idx    = inputs.indexOf(el);
                const next   = inputs[idx + dir];
                if (next) { next.focus(); next.select(); }
            },
        };
    }
    </script>
</x-app-layout>

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
                    @if ($readyToAssign < 0)−@endif${{ $demo->amt(abs($readyToAssign)) }}
                </p>
                @if ($readyToAssign < 0)
                    <p class="mt-2 text-sm text-white/80">Your envelope balances exceed your cash. Deposit money or reduce envelope funding to reconcile.</p>
                @elseif ($readyToAssign == 0)
                    <p class="mt-2 text-sm text-white/80">All money assigned — your budget is balanced.</p>
                @else
                    <p class="mt-2 text-sm text-white/80">Assign this to your envelopes below.</p>
                @endif
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400">
                Ready to assign = total spending account balance minus total envelope balances.
                <a href="{{ route('cash-accounts.index') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Add deposits in Spending Accounts</a> to increase this balance.
            </p>

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Assign to Envelopes</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Enter amounts to fund envelopes from your ready-to-assign balance.</p>
                        </div>

                        @if ($groups->isEmpty())
                            <div class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                <p>No envelopes yet.</p>
                                <a href="{{ route('envelopes.create') }}" class="mt-2 inline-block text-indigo-600 dark:text-indigo-400 hover:underline">Create your first envelope &rarr;</a>
                            </div>
                        @else
                            <div x-data="assignForm()">
                                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach ($groups as $groupName => $groupEnvelopes)
                                        <div class="px-6 py-2 bg-gray-50 dark:bg-gray-700/50">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ $groupName }}</p>
                                        </div>
                                        @foreach ($groupEnvelopes as $envelope)
                                            <div class="px-6 py-3 flex items-center gap-3">
                                                <span class="inline-block w-2.5 h-2.5 rounded-full shrink-0"
                                                      style="background-color: {{ $envelope->color }}"></span>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm text-gray-800 dark:text-gray-200 truncate">{{ $demo->n($envelope->name) }}</p>
                                                    <p class="text-xs text-gray-400 dark:text-gray-500 font-mono">
                                                        Balance:
                                                        <span x-text="'$' + (balances[{{ $envelope->id }}] ?? {{ (float) $envelope->current_balance }}).toFixed(2)">
                                                            ${{ $demo->amt($envelope->current_balance) }}
                                                        </span>
                                                        @if ($envelope->monthly_target)
                                                            · Target: ${{ $demo->amt($envelope->monthly_target) }}/mo
                                                        @endif
                                                    </p>
                                                </div>
                                                <div class="relative w-32 shrink-0">
                                                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 text-sm pointer-events-none">$</span>
                                                    <input type="number"
                                                           step="0.01"
                                                           min="0"
                                                           x-model="inputs[{{ $envelope->id }}]"
                                                           class="rta-input block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm pl-7 py-1.5"
                                                           :class="saved[{{ $envelope->id }}] ? 'border-green-400 dark:border-green-500' : ''"
                                                           @focus="onFocus({{ $envelope->id }}, $el)"
                                                           @blur="onBlur({{ $envelope->id }}, $el)"
                                                           @keydown.arrow-down.prevent="navigate($el, 1)"
                                                           @keydown.arrow-up.prevent="navigate($el, -1)"
                                                           @keydown.enter.prevent="onBlur({{ $envelope->id }}, $el)"
                                                           @keydown.tab="onBlur({{ $envelope->id }}, $el)">
                                                </div>
                                            </div>
                                        @endforeach
                                    @endforeach
                                </div>

                                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/40 text-sm text-gray-600 dark:text-gray-400">
                                    Ready to assign:
                                    <span class="font-mono font-semibold"
                                          :class="rta < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-800 dark:text-gray-200'"
                                          x-text="(rta < 0 ? '−' : '') + '$' + Math.abs(rta).toFixed(2)">
                                        ${{ $demo->amt($readyToAssign) }}
                                    </span>
                                    <span x-show="saving" class="ml-3 text-xs text-gray-400" x-cloak>Saving…</span>
                                    <span x-show="error" class="ml-3 text-xs text-red-500" x-text="error" x-cloak></span>
                                </div>
                            </div>
                        @endif
            </div>
        </div>
    </div>

    <script>
        function assignForm() {
            return {
                inputs:      @json($groups->flatten()->mapWithKeys(fn($e) => [$e->id => (float)$e->monthly_target ?: (float)$e->current_balance])),
                balances:    {},
                focusValues: {},
                saved:       {},
                saving:      false,
                error:       '',
                rta:         {{ round($demo->scaleScalar($readyToAssign), 2) }},

                onFocus(envelopeId, el) {
                    this.focusValues[envelopeId] = this.inputs[envelopeId];
                    el.select();
                },

                async onBlur(envelopeId, el) {
                    const current  = parseFloat(this.inputs[envelopeId]) || 0;
                    const original = parseFloat(this.focusValues[envelopeId]) || 0;
                    if (current === original || current <= 0) return;

                    this.saving = true;
                    this.error  = '';
                    try {
                        const res = await fetch('{{ route('ready-to-assign.assign-one') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ envelope_id: envelopeId, amount: current }),
                        });
                        if (!res.ok) throw new Error('Save failed');
                        const data = await res.json();
                        this.rta = data.ready_to_assign;
                        this.balances[envelopeId]    = data.envelope_balance;
                        this.focusValues[envelopeId] = current;
                        this.saved[envelopeId]       = true;
                        setTimeout(() => { this.saved[envelopeId] = false; }, 1500);
                    } catch {
                        this.error = 'Failed to save — check your connection.';
                    } finally {
                        this.saving = false;
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
</x-app-layout>

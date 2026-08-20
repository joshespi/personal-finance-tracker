{{--
    Upcoming/scheduled transactions panel.
    Params: $scheduled (Collection<ScheduledTransaction>), $demo, $heading (string),
            $emptyText (string), $showAccount (bool) — whether to print the cash account
            name in each row's subtitle (only meaningful in the cross-account ledger).
    Shared by transaction-list.blade.php (single account) and all-transactions.blade.php (aggregate).
--}}
<div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg mt-8">
    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $heading }}</h3>
        <a href="{{ route('scheduled-transactions.create') }}"
           class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">+ New</a>
    </div>

    @if ($scheduled->isEmpty())
        <div class="p-6 text-sm text-gray-500 dark:text-gray-400">
            {{ $emptyText }}
            <a href="{{ route('scheduled-transactions.create') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Create one</a>
            to auto-record recurring deposits or expenses.
        </div>
    @else
        <div class="divide-y divide-gray-100 dark:divide-gray-700">
            @foreach ($scheduled as $s)
                @php $isDue = $s->next_due_at->isToday(); $isOverdue = $s->next_due_at->isPast() && !$s->next_due_at->isToday(); @endphp
                <div class="px-6 py-3 flex items-center gap-4">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $s->description }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            {{ $s->typeLabel() }} · {{ $s->recurrenceLabel() }}
                            @if ($showAccount && $s->cashAccount)
                                · {{ $demo->n($s->cashAccount->name) }}
                            @endif
                            @if ($s->envelope)
                                · <span class="inline-block w-2 h-2 rounded-full align-middle" style="background-color: {{ $s->envelope->color }}"></span>
                                {{ $demo->n($s->envelope->name) }}
                            @endif
                        </p>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-sm font-mono font-semibold {{ $s->isInflow() ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">{{ $s->isInflow() ? '+' : '−' }}${{ $demo->amt((float)$s->amount) }}</p>
                        <p class="text-xs mt-0.5 {{ ($isDue || $isOverdue) ? 'text-amber-600 dark:text-amber-400 font-medium' : 'text-gray-500 dark:text-gray-400' }}">
                            @if ($isOverdue) Overdue · @elseif ($isDue) Due · @endif
                            {{ $s->next_due_at->format('M j, Y') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <form method="POST" action="{{ route('scheduled-transactions.enter-now', $s) }}"
                              {{-- @js(), not {{ }}: this is a JS string literal, and the HTML parser
                                   decodes &#039; back to a quote before the JS parser runs, so HTML
                                   escaping alone does not contain a description here. --}}
                              onsubmit="return confirm('Record ' + @js($s->description) + ' (${{ $demo->amt((float)$s->amount) }}) now and advance to the next cycle?')">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center px-2.5 py-1 bg-indigo-600 border border-transparent rounded-md text-xs font-semibold text-white hover:bg-indigo-500 transition">
                                Enter now
                            </button>
                        </form>
                        <form method="POST" action="{{ route('scheduled-transactions.skip', $s) }}"
                              onsubmit="return confirm('Skip this occurrence without recording it?')">
                            @csrf
                            <button type="submit"
                                    class="text-xs text-gray-400 dark:text-gray-500 hover:underline">Skip</button>
                        </form>
                        <a href="{{ route('scheduled-transactions.edit', $s) }}"
                           class="text-xs text-gray-400 dark:text-gray-500 hover:underline">Edit</a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

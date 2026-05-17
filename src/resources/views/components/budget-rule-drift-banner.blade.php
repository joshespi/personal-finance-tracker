@props(['drift', 'ratios', 'detailed' => false])

@if ($drift['mandatory_over'] || $drift['savings_under'])
    <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg px-5 {{ $detailed ? 'py-4 space-y-1.5' : 'py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2' }} text-sm text-amber-800 dark:text-amber-300">
        @if ($detailed)
            <p class="font-semibold">Your allocation is drifting from the 50/30/20 target:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @if ($drift['mandatory_over'])
                    <li>Mandatory spend is <strong>{{ $ratios['mandatory'] }}%</strong> of income — over the 50% ceiling. Either income is too low for your fixed costs, or some expenses tagged mandatory may not really be.</li>
                @endif
                @if ($drift['savings_under'])
                    <li>Savings is <strong>{{ $ratios['savings'] }}%</strong> of income — under the 20% target. Funding into savings envelopes (incl. emergency fund) hasn't kept pace.</li>
                @endif
            </ul>
        @else
            <div>
                <span class="font-semibold">50/30/20 drift:</span>
                @if ($drift['mandatory_over'])
                    Mandatory spend is {{ $ratios['mandatory'] }}% of income (target ≤ 50%).
                @endif
                @if ($drift['savings_under'])
                    Savings is {{ $ratios['savings'] }}% of income (target ≥ 20%).
                @endif
            </div>
            <a href="{{ route('budget-rule') }}" class="shrink-0 inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-800 border border-amber-300 dark:border-amber-600 rounded-md text-xs font-semibold text-amber-800 dark:text-amber-200 hover:bg-amber-100 dark:hover:bg-amber-900/40 transition">
                View breakdown &rarr;
            </a>
        @endif
    </div>
@endif

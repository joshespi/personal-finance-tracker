{{--
    Cleared + Pending = Working balance card.
    Params: $bal (array{cleared,uncleared,working}), $demo, $workingLabel (string)
    Shared by transaction-list.blade.php (single account) and all-transactions.blade.php (aggregate).
--}}
<div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4">
    <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
        <div>
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Cleared Balance</p>
            <p class="mt-1 text-xl font-semibold font-mono {{ $bal['cleared'] >= 0 ? 'text-gray-900 dark:text-gray-100' : 'text-red-600' }}">
                {{ $bal['cleared'] < 0 ? '−' : '' }}${{ $demo->amt(abs($bal['cleared'])) }}
            </p>
        </div>
        <span class="text-2xl text-gray-300 dark:text-gray-600 font-light">+</span>
        <div>
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Pending Balance</p>
            <p class="mt-1 text-xl font-semibold font-mono {{ $bal['uncleared'] == 0 ? 'text-gray-400 dark:text-gray-500' : ($bal['uncleared'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600') }}">
                {{ $bal['uncleared'] < 0 ? '−' : '' }}${{ $demo->amt(abs($bal['uncleared'])) }}
            </p>
        </div>
        <span class="text-2xl text-gray-300 dark:text-gray-600 font-light">=</span>
        <div>
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ $workingLabel }}</p>
            <p class="mt-1 text-3xl font-semibold font-mono {{ $bal['working'] >= 0 ? 'text-gray-900 dark:text-gray-100' : 'text-red-600' }}">
                {{ $bal['working'] < 0 ? '−' : '' }}${{ $demo->amt(abs($bal['working'])) }}
            </p>
        </div>
    </div>
</div>

@props([
    'action',            // POST route that records a new entry
    'entries',           // rows to list, newest first
    'destroy',           // fn ($entry) => delete route
    'field',             // numeric column on the entry: 'balance' | 'value'
    'dateField',         // date column on the entry: 'recorded_at' | 'valued_at'
    'formTitle',
    'historyTitle',
    'valueLabel',        // form label for the amount input
    'columnLabel',       // table header for the amount column
    'placeholder',
    'notesPlaceholder',
    'empty',
    'confirm',
    'hint' => null,
    'negative' => false, // render amounts as money owed (red, leading minus)
])

{{-- A "record a dated amount" form above a history table with per-row delete — shared by the
     liability balance log and the manual-asset valuation log. --}}
<div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $formTitle }}</h3>
    </div>
    <div class="p-6">
        <form method="POST" action="{{ $action }}" class="flex flex-wrap items-end gap-4">
            @csrf

            <div>
                <x-input-label :for="$field" :value="$valueLabel" />
                <x-text-input :id="$field" :name="$field" type="number" class="mt-1 block w-40"
                              :value="old($field)" required min="0" step="any" :placeholder="$placeholder" />
                @if ($hint)
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</p>
                @endif
                <x-input-error :messages="$errors->get($field)" class="mt-2" />
            </div>

            <div>
                <x-input-label :for="$dateField" value="Date" />
                <x-text-input :id="$dateField" :name="$dateField" type="date" class="mt-1 block w-40"
                              :value="old($dateField, now()->format('Y-m-d'))" required />
                <x-input-error :messages="$errors->get($dateField)" class="mt-2" />
            </div>

            <div class="flex-1 min-w-48">
                <x-input-label for="notes" value="Notes (optional)" />
                <x-text-input id="notes" name="notes" type="text" class="mt-1 block w-full"
                              :value="old('notes')" maxlength="1000" :placeholder="$notesPlaceholder" />
                <x-input-error :messages="$errors->get('notes')" class="mt-2" />
            </div>

            <div class="pb-0.5">
                <x-primary-button>Record</x-primary-button>
            </div>
        </form>
    </div>
</div>

<div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $historyTitle }}</h3>
    </div>

    @if ($entries->isEmpty())
        <div class="p-6 text-sm text-gray-500 dark:text-gray-400">{{ $empty }}</div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ $columnLabel }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Notes</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($entries as $entry)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-6 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $entry->{$dateField}->format('M j, Y') }}</td>
                            <td class="px-6 py-3 text-right font-mono font-semibold {{ $negative ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100' }}">{{ $negative ? '−' : '' }}{{ $demo->amt((float) $entry->{$field}) }}</td>
                            <td class="px-6 py-3 text-gray-500 dark:text-gray-400">{{ $entry->notes ?? '—' }}</td>
                            <td class="px-6 py-3 text-right">
                                <x-delete-form :action="$destroy($entry)" :confirm="$confirm" class="inline" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    <a href="{{ route('liabilities.index') }}" class="hover:underline">Liabilities</a>
                </p>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ $demo->n($liability->name) }}</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ $liabilityTypes[$liability->liability_type] ?? $liability->liability_type }}
                    @if ($liability->manualAsset)
                        &bull; secured by <a href="{{ route('manual-assets.show', $liability->manualAsset) }}" class="hover:underline">{{ $demo->n($liability->manualAsset->name) }}</a>
                    @endif
                    @if ($liability->interest_rate !== null)
                        &bull; {{ rtrim(rtrim(number_format((float)$liability->interest_rate, 3), '0'), '.') }}% APR
                    @endif
                    &bull; {{ $liability->currency }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <x-btn variant="secondary" href="{{ route('liabilities.edit', $liability) }}">
                    Edit
                </x-btn>
                <x-delete-form :action="route('liabilities.destroy', $liability)" confirm="Delete this liability and all its balance history?" variant="button" />
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="sm:px-6 lg:px-8 space-y-8">

            <x-flash />

            @if ($liability->notes)
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $liability->notes }}</p>
                </div>
            @endif

            <x-dated-entry-log
                :action="route('liabilities.balances.store', $liability)"
                :entries="$liability->balances"
                :destroy="fn ($b) => route('liabilities.balances.destroy', $b)"
                field="balance" date-field="recorded_at" negative
                form-title="Record New Balance" history-title="Balance History"
                :value-label="'Balance Owed ('.$liability->currency.')'"
                :column-label="'Balance ('.$liability->currency.')'"
                placeholder="245000.00" notes-placeholder="Monthly statement"
                hint="Enter as a positive amount, e.g. 245000.00."
                empty="No balances recorded yet." confirm="Delete this balance entry?" />

        </div>
    </div>
</x-app-layout>

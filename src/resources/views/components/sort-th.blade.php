{{--
    Sortable table header for the cash ledgers. Clicking calls the Livewire host's
    sortBy(); the arrow shows direction on the active column only.
    Params: $field (SORTS key), $label, $current/$direction (the host's sort state),
            $align ('left'|'right').
--}}
@props(['field', 'label', 'current', 'direction', 'align' => 'left'])

@php
    $active = $current === $field;
@endphp

<th @class([
        'px-4 py-3 text-xs font-medium uppercase',
        'text-left'  => $align === 'left',
        'text-right' => $align === 'right',
        'text-gray-900 dark:text-gray-100'  => $active,
        'text-gray-500 dark:text-gray-400'  => ! $active,
    ])
    aria-sort="{{ $active ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
    <button type="button" wire:click="sortBy('{{ $field }}')"
            @class([
                'group inline-flex items-center gap-1 uppercase hover:text-gray-900 dark:hover:text-gray-100 transition-colors',
                'flex-row-reverse' => $align === 'right',
            ])
            title="Sort by {{ $label }}">
        <span>{{ $label }}</span>
        {{-- Inactive columns keep the glyph in the layout, revealed faintly on hover. --}}
        <span @class([
                'text-[0.65rem] leading-none',
                'opacity-0 group-hover:opacity-40 transition-opacity' => ! $active,
            ])>{{ $active && $direction === 'asc' ? '▲' : '▼' }}</span>
    </button>
</th>

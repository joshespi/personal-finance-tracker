@props(['cleared'])

{{-- Cleared/Pending status pill for a cash-ledger row. Shared by the view row and the
     inline edit row so status reads identically in both modes; the caller supplies its
     own wire:click (and any extra attributes) via the attribute bag. --}}
@php
    [$dot, $badge, $label, $title] = $cleared
        ? ['bg-green-500', 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300 hover:bg-green-200 dark:hover:bg-green-900/70', 'Cleared', 'Cleared — click to mark pending']
        : ['bg-amber-500', 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300 hover:bg-amber-200 dark:hover:bg-amber-900/70', 'Pending', 'Pending — click to mark cleared'];
@endphp

<button type="button" title="{{ $title }}" aria-pressed="{{ $cleared ? 'true' : 'false' }}"
        {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium whitespace-nowrap transition '.$badge]) }}>
    <span class="w-1.5 h-1.5 rounded-full {{ $dot }}"></span> {{ $label }}
</button>

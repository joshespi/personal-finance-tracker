@props(['variant' => 'secondary'])

{{-- Compact action button for table rows and page headers. Renders an <a> when given an href, otherwise a <button>.
     The variant strings are written out in full (not assembled) so Tailwind's scanner sees every class. --}}
@php
    $classes = [
        'secondary' => 'inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition',
        'danger'    => 'inline-flex items-center px-3 py-1.5 bg-red-600 border border-transparent rounded-md text-xs font-semibold text-white hover:bg-red-500 transition',
        'primary'   => 'inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded-md text-xs font-semibold text-white hover:bg-indigo-500 transition',
    ];
    $classes = $classes[$variant] ?? $classes['secondary'];
@endphp

@if ($attributes->has('href'))
    <a {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif

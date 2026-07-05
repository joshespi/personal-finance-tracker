@props(['active' => false])

@php
$base = 'group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition';
$state = ($active ?? false)
    ? 'bg-indigo-50 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300'
    : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-gray-100';
@endphp

<a {{ $attributes->merge(['class' => $base.' '.$state]) }}>
    {{ $slot }}
</a>

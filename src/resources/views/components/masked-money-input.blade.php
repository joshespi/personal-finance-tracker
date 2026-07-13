@props(['name', 'value', 'masked' => false])

{{--
    A currency form input that, in demo mode, shows a masked placeholder while still
    submitting the real value via a hidden field (so recomputes use the true number
    instead of a masked one). Pass through step/min/placeholder etc. as attributes.
--}}
@if ($masked)
    <input type="text" value="••••••" disabled
           class="pl-7 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-600 dark:text-gray-400 bg-gray-100 rounded-md shadow-sm text-sm cursor-not-allowed" />
    <input type="hidden" name="{{ $name }}" value="{{ $value }}" />
@else
    <input type="number" name="{{ $name }}" value="{{ $value }}"
           {{ $attributes->merge(['class' => 'pl-7 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm']) }} />
@endif

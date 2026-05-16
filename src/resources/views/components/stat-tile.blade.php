@props(['highlight' => false])
<div @class(['bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4', 'ring-1 ring-indigo-500/20' => $highlight])>
    @isset($label)
        <p @class(['text-xs uppercase tracking-wide', 'text-gray-500 dark:text-gray-400' => !$highlight, 'text-indigo-600 dark:text-indigo-400 font-semibold' => $highlight])>
            {{ $label }}
        </p>
    @endisset
    {{ $slot }}
</div>

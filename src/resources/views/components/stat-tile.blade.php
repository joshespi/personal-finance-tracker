@props(['highlight' => false])
<div {{ $attributes->class(['shadow-sm border-t-2 rounded-lg px-4 py-4 text-start overflow-hidden', 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600' => !$highlight, 'bg-indigo-50 dark:bg-indigo-900 border-indigo-500 dark:border-indigo-400 ring-1 ring-indigo-500/20' => $highlight]) }}>
    @isset($label)
        <p {{ $label->attributes }} @class(['text-xs uppercase tracking-wide truncate', 'text-gray-500 dark:text-gray-400' => !$highlight, 'text-indigo-600 dark:text-indigo-400 font-semibold' => $highlight])>
            {{ $label }}
        </p>
    @endisset
    <div class="[&>p:first-of-type]:!text-[clamp(0.8rem,1.35vw,1.25rem)]">
        {{ $slot }}
    </div>
    @isset($caption)
        <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500 truncate">{{ $caption }}</p>
    @endisset
</div>

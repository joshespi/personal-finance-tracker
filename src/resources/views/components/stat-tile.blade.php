@props(['highlight' => false])
<div {{ $attributes->class(['bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-5 py-4 text-center overflow-hidden', 'ring-1 ring-indigo-500/20' => $highlight]) }}>
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

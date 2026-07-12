@props(['id', 'ranges'])
<div class="flex flex-wrap gap-1" id="{{ $id }}">
    @foreach ($ranges as $r)
        <button data-range="{{ $r }}"
                class="px-2.5 py-1 text-xs rounded font-medium transition
                       bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300
                       hover:bg-gray-200 dark:hover:bg-gray-600">
            {{ $r }}
        </button>
    @endforeach
</div>

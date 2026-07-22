@props(['tabs'])
<div class="flex items-center gap-1">
    @foreach ($tabs as $tab)
        <a href="{{ $tab['href'] }}"
           class="px-3 py-1.5 text-xs rounded-md font-medium transition
                  {{ $tab['active']
                      ? 'bg-gray-800 dark:bg-gray-200 text-white dark:text-gray-900'
                      : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 border border-gray-200 dark:border-gray-600' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>

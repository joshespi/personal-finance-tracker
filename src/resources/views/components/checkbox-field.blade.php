@props(['name', 'label', 'help' => null, 'model' => null, 'default' => true])

<div class="flex items-center gap-3">
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox" id="{{ $name }}" name="{{ $name }}" value="1"
           class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500"
           @checked(old($name, $model?->{$name} ?? $default)) />
    <div>
        <x-input-label :for="$name" :value="$label" class="cursor-pointer" />
        @if ($help)
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $help }}</p>
        @endif
    </div>
</div>

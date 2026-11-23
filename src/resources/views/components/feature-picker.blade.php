@props(['selected' => null, 'error' => null])

{{-- One checkbox per toggleable module. $selected: list of Feature values currently on
     (null = all on). Shared by registration and the profile Features section. --}}
@php
    $selected = $selected ?? \App\Enums\Feature::values();
@endphp
<div class="space-y-3">
    @foreach (\App\Enums\Feature::cases() as $feature)
        <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" name="features[]" value="{{ $feature->value }}"
                   class="mt-1 rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500"
                   {{ in_array($feature->value, $selected, true) ? 'checked' : '' }}>
            <span>
                <span class="block text-sm font-medium text-gray-800 dark:text-gray-200">{{ $feature->label() }}</span>
                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $feature->description() }}</span>
            </span>
        </label>
    @endforeach
    @if ($error)
        <p class="text-sm text-red-600 dark:text-red-400">{{ $error }}</p>
    @endif
</div>

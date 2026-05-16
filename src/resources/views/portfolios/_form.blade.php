@php $p = $portfolio ?? null; @endphp

<div>
    <x-input-label for="name" value="Name" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                  :value="old('name', $p?->name)" required autofocus maxlength="100" />
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
</div>

<div>
    <x-input-label for="description" value="Description (optional)" />
    <textarea id="description" name="description"
              class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
              rows="3" maxlength="1000">{{ old('description', $p?->description) }}</textarea>
    <x-input-error :messages="$errors->get('description')" class="mt-2" />
</div>

<div>
    <x-input-label for="currency" value="Base Currency" />
    <x-text-input id="currency" name="currency" type="text" class="mt-1 block w-32"
                  :value="old('currency', $p?->currency ?? 'USD')" required maxlength="3"
                  placeholder="USD" style="text-transform:uppercase" />
    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">3-letter ISO code, e.g. USD, EUR, GBP</p>
    <x-input-error :messages="$errors->get('currency')" class="mt-2" />
</div>


<div class="flex items-center gap-3">
    <input id="is_tax_advantaged" name="is_tax_advantaged" type="checkbox" value="1"
           class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm"
           {{ old('is_tax_advantaged', $p?->is_tax_advantaged) ? 'checked' : '' }}>
    <label for="is_tax_advantaged" class="text-sm text-gray-700 dark:text-gray-300">
        Tax-advantaged account (401k, IRA, HSA, etc.) — excluded from tax summary
    </label>
</div>

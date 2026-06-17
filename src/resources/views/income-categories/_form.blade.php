@php $c = $category ?? null; @endphp

<div>
    <x-input-label for="name" value="Name" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                  :value="old('name', $c?->name)" required autofocus maxlength="100"
                  placeholder="Salary, Bonus, Gift, Refund…" />
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
</div>

<div class="flex flex-wrap gap-4">
    <div>
        <x-input-label for="color" value="Color" />
        <input id="color" name="color" type="color"
               value="{{ old('color', $c?->color ?? '#6366f1') }}"
               class="mt-1 block h-10 w-20 rounded-md border-gray-300 dark:border-gray-600">
        <x-input-error :messages="$errors->get('color')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="sort_order" value="Sort Order" />
        <x-text-input id="sort_order" name="sort_order" type="number" class="mt-1 block w-24"
                      :value="old('sort_order', $c?->sort_order ?? 0)" min="0" step="1" />
        <x-input-error :messages="$errors->get('sort_order')" class="mt-2" />
    </div>
</div>

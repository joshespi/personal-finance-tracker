@php $a = $account ?? null; @endphp

<div>
    <x-input-label for="name" value="Name" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                  :value="old('name', $a?->name)" required autofocus maxlength="200"
                  placeholder="Chase Checking" />
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
</div>

<div>
    <x-input-label for="account_type" value="Type" />
    <select id="account_type" name="account_type"
            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
        @foreach ($accountTypes as $value => $label)
            <option value="{{ $value }}" @selected(old('account_type', $a?->account_type) === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('account_type')" class="mt-2" />
</div>

<div>
    <x-input-label for="currency" value="Currency" />
    <x-text-input id="currency" name="currency" type="text" class="mt-1 block w-32"
                  :value="old('currency', $a?->currency ?? 'USD')" required maxlength="3"
                  placeholder="USD" style="text-transform:uppercase" />
    <x-input-error :messages="$errors->get('currency')" class="mt-2" />
</div>

<div>
    <x-input-label for="notes" value="Notes (optional)" />
    <textarea id="notes" name="notes"
              class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
              rows="3" maxlength="1000">{{ old('notes', $a?->notes) }}</textarea>
    <x-input-error :messages="$errors->get('notes')" class="mt-2" />
</div>

@php $l = $liability ?? null; @endphp

<div>
    <x-input-label for="name" value="Name" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                  :value="old('name', $l?->name)" required autofocus maxlength="200"
                  placeholder="Mortgage on 123 Main St" />
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
</div>

<div>
    <x-input-label for="liability_type" value="Type" />
    <select id="liability_type" name="liability_type"
            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
        @foreach ($liabilityTypes as $value => $label)
            <option value="{{ $value }}" @selected(old('liability_type', $l?->liability_type) === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('liability_type')" class="mt-2" />
</div>

<div>
    <x-input-label for="manual_asset_id" value="Secured by Asset (optional)" />
    <select id="manual_asset_id" name="manual_asset_id"
            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
        <option value="">— None —</option>
        @foreach ($manualAssets as $ma)
            <option value="{{ $ma->id }}" @selected((string)old('manual_asset_id', $l?->manual_asset_id) === (string)$ma->id)>{{ $ma->name }}</option>
        @endforeach
    </select>
    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Link a mortgage to a property, or an auto loan to a vehicle.</p>
    <x-input-error :messages="$errors->get('manual_asset_id')" class="mt-2" />
</div>

<div class="flex flex-wrap gap-4">
    <div>
        <x-input-label for="interest_rate" value="Interest Rate % (optional)" />
        <x-text-input id="interest_rate" name="interest_rate" type="number" class="mt-1 block w-32"
                      :value="old('interest_rate', $l?->interest_rate)" min="0" max="100" step="0.001"
                      placeholder="6.250" />
        <x-input-error :messages="$errors->get('interest_rate')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="minimum_payment" value="Minimum Payment (optional)" />
        <x-text-input id="minimum_payment" name="minimum_payment" type="number" class="mt-1 block w-36"
                      :value="old('minimum_payment', $l?->minimum_payment)" min="0" step="0.01"
                      placeholder="25.00" />
        <x-input-error :messages="$errors->get('minimum_payment')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="currency" value="Currency" />
        <x-text-input id="currency" name="currency" type="text" class="mt-1 block w-32"
                      :value="old('currency', $l?->currency ?? 'USD')" required maxlength="3"
                      placeholder="USD" style="text-transform:uppercase" />
        <x-input-error :messages="$errors->get('currency')" class="mt-2" />
    </div>
</div>

<div>
    <x-input-label for="notes" value="Notes (optional)" />
    <textarea id="notes" name="notes"
              class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
              rows="3" maxlength="1000">{{ old('notes', $l?->notes) }}</textarea>
    <x-input-error :messages="$errors->get('notes')" class="mt-2" />
</div>

@php $a = $account ?? null; @endphp

<div x-data="{ accountType: '{{ old('account_type', $a?->account_type ?? '') }}' }" class="contents">

<div>
    <x-input-label for="name" value="Name" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                  :value="old('name', $a?->name)" required autofocus maxlength="200"
                  placeholder="Chase Checking" />
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
</div>

<div>
    <x-input-label for="account_type" value="Type" />
    <x-select-input id="account_type" name="account_type"
            x-model="accountType">
        @foreach ($accountTypes as $value => $label)
            <option value="{{ $value }}" @selected(old('account_type', $a?->account_type) === $value)>{{ $label }}</option>
        @endforeach
    </x-select-input>
    <x-input-error :messages="$errors->get('account_type')" class="mt-2" />
</div>

<div>
    <x-input-label for="currency" value="Currency" />
    <x-text-input id="currency" name="currency" type="text" class="mt-1 block w-32"
                  :value="old('currency', $a?->currency ?? 'USD')" required maxlength="3"
                  placeholder="USD" style="text-transform:uppercase" />
    <x-input-error :messages="$errors->get('currency')" class="mt-2" />
</div>

<div x-show="accountType === 'credit_card'" x-cloak class="grid grid-cols-2 gap-4">
    <div>
        <x-input-label for="interest_rate" value="APR (%)" />
        <x-text-input id="interest_rate" name="interest_rate" type="number" class="mt-1 block w-full"
                      :value="old('interest_rate', $a?->interest_rate)" min="0" max="999.99" step="0.01"
                      placeholder="24.99" />
        <x-input-error :messages="$errors->get('interest_rate')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="billing_day" value="Statement closes (day of month)" />
        <x-text-input id="billing_day" name="billing_day" type="number" class="mt-1 block w-full"
                      :value="old('billing_day', $a?->billing_day)" min="1" max="28" step="1"
                      placeholder="15" />
        <x-input-error :messages="$errors->get('billing_day')" class="mt-2" />
    </div>
</div>

<div>
    <x-input-label for="notes" value="Notes (optional)" />
    <x-textarea-input id="notes" name="notes" rows="3" maxlength="1000">{{ old('notes', $a?->notes) }}</x-textarea-input>
    <x-input-error :messages="$errors->get('notes')" class="mt-2" />
</div>

</div>

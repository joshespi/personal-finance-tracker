@php $s = $scheduledTransaction ?? null; @endphp

<div x-data="{
    type: '{{ old('type', $s?->type ?? 'envelope_fund') }}',
    needsEnvelope()  { return ['envelope_fund','envelope_spend'].includes(this.type); },
    needsCash()      { return ['cash_deposit','cash_withdrawal','envelope_fund'].includes(this.type); },
    cashRequired()   { return ['cash_deposit','cash_withdrawal'].includes(this.type); },
}" class="space-y-5">

    {{-- Description --}}
    <div>
        <x-input-label for="description" value="Description" />
        <x-text-input id="description" name="description" type="text" class="mt-1 block w-full"
                      :value="old('description', $s?->description)" required maxlength="500"
                      placeholder="Rent, Salary, Netflix…" />
        <x-input-error :messages="$errors->get('description')" class="mt-2" />
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        {{-- Amount --}}
        <div>
            <x-input-label for="amount" value="Amount" />
            <x-text-input id="amount" name="amount" type="number" class="mt-1 block w-full"
                          :value="old('amount', $s ? (float)$s->amount : null)"
                          required min="0.01" step="any" placeholder="0.00" />
            <x-input-error :messages="$errors->get('amount')" class="mt-2" />
        </div>

        {{-- Type --}}
        <div>
            <x-input-label for="type" value="Type" />
            <select id="type" name="type" x-model="type"
                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                <option value="envelope_fund">Fund envelope</option>
                <option value="envelope_spend">Envelope spend</option>
                <option value="cash_deposit">Cash deposit</option>
                <option value="cash_withdrawal">Cash withdrawal</option>
            </select>
            <x-input-error :messages="$errors->get('type')" class="mt-2" />
        </div>

        {{-- Recurrence --}}
        <div>
            <x-input-label for="recurrence" value="Recurrence" />
            <select id="recurrence" name="recurrence"
                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                <option value="monthly" @selected(old('recurrence', $s?->recurrence) === 'monthly')>Monthly</option>
                <option value="weekly" @selected(old('recurrence', $s?->recurrence) === 'weekly')>Weekly</option>
                <option value="biweekly" @selected(old('recurrence', $s?->recurrence) === 'biweekly')>Biweekly (every 2 weeks)</option>
            </select>
            <x-input-error :messages="$errors->get('recurrence')" class="mt-2" />
        </div>

        {{-- Next due --}}
        <div>
            <x-input-label for="next_due_at" value="Next due date" />
            <x-text-input id="next_due_at" name="next_due_at" type="date" class="mt-1 block w-full"
                          :value="old('next_due_at', $s?->next_due_at?->format('Y-m-d'))" required />
            <x-input-error :messages="$errors->get('next_due_at')" class="mt-2" />
        </div>
    </div>

    {{-- Envelope --}}
    <div x-show="needsEnvelope()" x-cloak>
        <x-input-label for="envelope_id" value="Envelope" />
        <select id="envelope_id" name="envelope_id"
                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
            <option value="">— Select envelope —</option>
            @foreach ($envelopes as $env)
                <option value="{{ $env->id }}" @selected((string)old('envelope_id', $s?->envelope_id) === (string)$env->id)>
                    {{ $env->name }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('envelope_id')" class="mt-2" />
    </div>

    {{-- Cash account --}}
    <div x-show="needsCash()" x-cloak>
        <x-input-label for="cash_account_id">
            Cash account <span x-show="!cashRequired()" class="text-gray-400 dark:text-gray-500 font-normal">(optional)</span>
        </x-input-label>
        <select id="cash_account_id" name="cash_account_id"
                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
            <option value="">— None —</option>
            @foreach ($cashAccounts as $ca)
                <option value="{{ $ca->id }}" @selected((string)old('cash_account_id', $s?->cash_account_id) === (string)$ca->id)>
                    {{ $ca->name }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('cash_account_id')" class="mt-2" />
        <p x-show="type === 'envelope_fund' && needsCash()" class="mt-1 text-xs text-gray-400 dark:text-gray-500">
            Pairing a cash account will create a matching withdrawal there each time this runs.
        </p>
    </div>

    @if ($s)
        {{-- Active toggle on edit --}}
        <div class="flex items-center gap-3">
            <input id="is_active" name="is_active" type="checkbox" value="1"
                   @checked(old('is_active', $s->is_active))
                   class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500" />
            <x-input-label for="is_active" value="Active" class="!mb-0" />
        </div>
    @endif

</div>

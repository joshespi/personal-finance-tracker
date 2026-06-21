@php
    $e = $envelope ?? null;
    // Trim extraneous trailing zeros from decimal amounts (e.g. "200.00000000" -> "200").
    $trimAmount = fn ($v) => $v === null ? null : rtrim(rtrim((string) $v, '0'), '.');
@endphp

<div>
    <x-input-label for="name" value="Name" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                  :value="old('name', $e?->name)" required autofocus maxlength="200"
                  placeholder="Groceries" />
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
</div>

<div class="flex flex-wrap gap-4">
    <div>
        <x-input-label for="monthly_target" value="Monthly Budget (optional)" />
        <x-text-input id="monthly_target" name="monthly_target" type="number" class="mt-1 block w-40"
                      :value="old('monthly_target', $trimAmount($e?->monthly_target))" min="0" step="any" placeholder="500.00" />
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Max to spend per month</p>
        <x-input-error :messages="$errors->get('monthly_target')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="goal_amount" value="Savings Goal (optional)" />
        <x-text-input id="goal_amount" name="goal_amount" type="number" class="mt-1 block w-40"
                      :value="old('goal_amount', $trimAmount($e?->goal_amount))" min="0" step="any" placeholder="10000.00" />
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Target balance to accumulate</p>
        <x-input-error :messages="$errors->get('goal_amount')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="goal_date" value="Goal Date (optional)" />
        <x-text-input id="goal_date" name="goal_date" type="date" class="mt-1 block w-40"
                      :value="old('goal_date', $e?->goal_date?->format('Y-m-d'))" />
        <x-input-error :messages="$errors->get('goal_date')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="color" value="Color" />
        <input id="color" name="color" type="color"
               value="{{ old('color', $e?->color ?? '#6366f1') }}"
               class="mt-1 block h-10 w-20 rounded-md border-gray-300 dark:border-gray-600">
        <x-input-error :messages="$errors->get('color')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="sort_order" value="Sort Order" />
        <x-text-input id="sort_order" name="sort_order" type="number" class="mt-1 block w-24"
                      :value="old('sort_order', $e?->sort_order ?? 0)" min="0" step="1" />
        <x-input-error :messages="$errors->get('sort_order')" class="mt-2" />
    </div>
</div>

<div>
    <x-input-label for="notes" value="Notes (optional)" />
    <textarea id="notes" name="notes"
              class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
              rows="3" maxlength="1000">{{ old('notes', $e?->notes) }}</textarea>
    <x-input-error :messages="$errors->get('notes')" class="mt-2" />
</div>

<div class="flex flex-col gap-2.5">
    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
        Budget category <span class="font-normal">(optional — leave all unchecked for a discretionary "want")</span>
    </p>
    <label class="flex items-center gap-2.5 cursor-pointer">
        <input type="checkbox" name="is_mandatory" value="1"
               {{ old('is_mandatory', $e?->is_mandatory) ? 'checked' : '' }}
               class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500">
        <span class="text-sm text-gray-700 dark:text-gray-300">Necessity</span>
        <span class="text-xs text-gray-400 dark:text-gray-500">(a must-pay essential — counts as a 50% Need &amp; sizes your emergency-fund goal)</span>
    </label>
    <label class="flex items-center gap-2.5 cursor-pointer">
        <input type="checkbox" name="is_savings" value="1"
               {{ old('is_savings', $e?->is_savings) ? 'checked' : '' }}
               class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500">
        <span class="text-sm text-gray-700 dark:text-gray-300">Wealth building</span>
        <span class="text-xs text-gray-400 dark:text-gray-500"
              title="Use this for retirement contributions and long-term investing — not sinking funds for purchases like a vacation or gadget. Those are deferred wants and belong in your 30% discretionary budget.">(long-term savings &amp; investing — counts toward your 20% Savings ⓘ)</span>
    </label>
    <label class="flex items-center gap-2.5 cursor-pointer">
        <input type="checkbox" name="is_emergency_fund" value="1"
               {{ old('is_emergency_fund', $e?->is_emergency_fund) ? 'checked' : '' }}
               class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500">
        <span class="text-sm text-gray-700 dark:text-gray-300">This is my emergency fund</span>
        <span class="text-xs text-gray-400 dark:text-gray-500">(its balance is your actual emergency fund — only one envelope)</span>
    </label>
</div>

@php $p = $pension ?? null; @endphp

<div x-data="{ advanced: {{ $p && ($p->monthly_benefit_estimate !== null || $p->service_cap_years !== null || (float) $p->salary_growth_pct > 0) ? 'true' : 'false' }} }" class="space-y-6">

    <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-700 rounded-lg px-4 py-3 text-xs text-indigo-800 dark:text-indigo-300 space-y-1">
        <p class="font-semibold">Reading this off your URS statement</p>
        <p><strong>Service credit</strong> = URS "Unverified Service Credit". <strong>Multiplier</strong> = 2% for Tier 1, 1.5% for Tier 2 Public Employees.
           <strong>Final average salary</strong> = your highest-3-year (Tier 1) or highest-5-year (Tier 2) average — not your current YTD salary.
           If you've run URS's <strong>Benefit Estimate</strong>, enter that exact monthly amount under Advanced and it overrides the formula.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-4">
        <div class="sm:col-span-2">
            <x-input-label for="name" value="Name" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                          :value="old('name', $p?->name ?? 'URS Pension')" required autofocus maxlength="200" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="sm:col-span-2">
            <x-input-label for="plan_label" value="Plan / Tier (optional)" />
            <x-text-input id="plan_label" name="plan_label" type="text" class="mt-1 block w-full"
                          :value="old('plan_label', $p?->plan_label)" maxlength="200"
                          placeholder="Tier 1 Public Employees" />
            <x-input-error :messages="$errors->get('plan_label')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="service_credit_years" value="Service Credit (years)" />
            <x-text-input id="service_credit_years" name="service_credit_years" type="number" class="mt-1 block w-full"
                          :value="old('service_credit_years', $p?->service_credit_years)" required min="0" max="100" step="0.001"
                          placeholder="16.588" />
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">URS "Unverified Service Credit".</p>
            <x-input-error :messages="$errors->get('service_credit_years')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="multiplier_pct" value="Multiplier % per year" />
            <x-text-input id="multiplier_pct" name="multiplier_pct" type="number" class="mt-1 block w-full"
                          :value="old('multiplier_pct', $p?->multiplier_pct ?? '2.000')" required min="0" max="10" step="0.001"
                          placeholder="2.000" />
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Tier 1 = 2%, Tier 2 = 1.5%.</p>
            <x-input-error :messages="$errors->get('multiplier_pct')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="final_average_salary" value="Final Average Salary" />
            <div class="relative mt-1">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 text-sm">$</span>
                <input id="final_average_salary" name="final_average_salary" type="number" min="0" step="0.01" required
                       value="{{ old('final_average_salary', $p?->final_average_salary) }}" placeholder="100000"
                       class="pl-7 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
            </div>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Highest 3- (Tier 1) or 5-year (Tier 2) average.</p>
            <x-input-error :messages="$errors->get('final_average_salary')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="birth_year" value="Birth Year" />
            <x-text-input id="birth_year" name="birth_year" type="number" class="mt-1 block w-full"
                          :value="old('birth_year', $p?->birth_year)" min="1930" max="{{ now()->year - 18 }}"
                          placeholder="{{ now()->year - 40 }}" />
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Used for age &amp; years-until-draw. Stored.</p>
            <x-input-error :messages="$errors->get('birth_year')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="retirement_age" value="Retire / Draw at Age" />
            <x-text-input id="retirement_age" name="retirement_age" type="number" class="mt-1 block w-full"
                          :value="old('retirement_age', $p?->retirement_age ?? 52)" required min="40" max="90"
                          placeholder="52" />
            <x-input-error :messages="$errors->get('retirement_age')" class="mt-2" />
        </div>
    </div>

    <button type="button" @click="advanced = !advanced"
            class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
        <span x-show="!advanced">Show advanced assumptions ▾</span>
        <span x-show="advanced" x-cloak>Hide advanced assumptions ▴</span>
    </button>

    <div x-show="advanced" x-cloak class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-4">
            <div>
                <x-input-label for="monthly_benefit_estimate" value="URS Benefit Estimate ($/mo, optional)" />
                <div class="relative mt-1">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 text-sm">$</span>
                    <input id="monthly_benefit_estimate" name="monthly_benefit_estimate" type="number" min="0" step="0.01"
                           value="{{ old('monthly_benefit_estimate', $p?->monthly_benefit_estimate) }}" placeholder="e.g. 4200.00"
                           class="pl-7 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                </div>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">If set, this exact figure overrides the service × multiplier × FAS formula.</p>
                <x-input-error :messages="$errors->get('monthly_benefit_estimate')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="membership_date" value="Membership Date (optional)" />
                <x-text-input id="membership_date" name="membership_date" type="date" class="mt-1 block w-full"
                              :value="old('membership_date', $p?->membership_date?->format('Y-m-d'))" />
                <x-input-error :messages="$errors->get('membership_date')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="cola_pct" value="COLA cap %" />
                <x-text-input id="cola_pct" name="cola_pct" type="number" class="mt-1 block w-full"
                              :value="old('cola_pct', $p?->cola_pct ?? '4.00')" min="0" max="20" step="0.01"
                              placeholder="4.00" />
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Tier 1 up to 4%, Tier 2 up to 2.5%. Values as a real (COLA'd) benefit.</p>
                <x-input-error :messages="$errors->get('cola_pct')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="discount_rate_pct" value="Real discount rate %" />
                <x-text-input id="discount_rate_pct" name="discount_rate_pct" type="number" class="mt-1 block w-full"
                              :value="old('discount_rate_pct', $p?->discount_rate_pct ?? '2.00')" required min="0" max="10" step="0.01"
                              placeholder="2.00" />
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">For a COLA'd pension use a real rate (~long TIPS yield). PV is very sensitive to this.</p>
                <x-input-error :messages="$errors->get('discount_rate_pct')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="life_expectancy_age" value="Life expectancy age" />
                <x-text-input id="life_expectancy_age" name="life_expectancy_age" type="number" class="mt-1 block w-full"
                              :value="old('life_expectancy_age', $p?->life_expectancy_age ?? 90)" required min="50" max="110"
                              placeholder="90" />
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Sets the payout horizon for the life-annuity value.</p>
                <x-input-error :messages="$errors->get('life_expectancy_age')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="salary_growth_pct" value="Real salary growth % / yr" />
                <x-text-input id="salary_growth_pct" name="salary_growth_pct" type="number" class="mt-1 block w-full"
                              :value="old('salary_growth_pct', $p?->salary_growth_pct ?? '0')" min="0" max="20" step="0.01"
                              placeholder="0" />
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Above inflation. Grows FAS toward retirement; leave 0 to value in today's dollars.</p>
                <x-input-error :messages="$errors->get('salary_growth_pct')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="service_cap_years" value="Service cap (years, optional)" />
                <x-text-input id="service_cap_years" name="service_cap_years" type="number" class="mt-1 block w-full"
                              :value="old('service_cap_years', $p?->service_cap_years)" min="0" max="100" step="0.001"
                              placeholder="none" />
                <x-input-error :messages="$errors->get('service_cap_years')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="currency" value="Currency" />
                <x-text-input id="currency" name="currency" type="text" class="mt-1 block w-full"
                              :value="old('currency', $p?->currency ?? 'USD')" required maxlength="3"
                              style="text-transform:uppercase" />
                <x-input-error :messages="$errors->get('currency')" class="mt-2" />
            </div>
        </div>

        <div>
            <x-input-label for="notes" value="Notes (optional)" />
            <textarea id="notes" name="notes" rows="2" maxlength="1000"
                      class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes', $p?->notes) }}</textarea>
            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
        </div>

        <label class="inline-flex items-center">
            <input type="checkbox" name="include_in_net_worth" value="1"
                   @checked(old('include_in_net_worth', $p?->include_in_net_worth ?? true))
                   class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500" />
            <span class="ms-2 text-sm text-gray-700 dark:text-gray-300">Count accrued present value toward Dashboard net worth</span>
        </label>
        <p class="text-xs text-gray-400 dark:text-gray-500 -mt-2">Shown as a segregated "Future Income (PV)" line — never enters allocation or rebalancing.</p>
    </div>

</div>

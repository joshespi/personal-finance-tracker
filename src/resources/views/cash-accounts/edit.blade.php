<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit Account" :crumbs="[['Spending Accounts', route('cash-accounts.index')], [$account->name, route('cash-accounts.show', $account)]]" />
    </x-slot>

    <div class="py-12">
        <div class="sm:px-6 lg:px-8">
            <x-form-card :action="route('cash-accounts.update', $account)" method="PUT" submit="Save" :cancel="route('cash-accounts.show', $account)">
                @include('cash-accounts._form')
            </x-form-card>
        </div>
    </div>
</x-app-layout>

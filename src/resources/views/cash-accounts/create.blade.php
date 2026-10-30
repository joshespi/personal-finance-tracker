<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Add Spending Account" :crumbs="[['Spending Accounts', route('cash-accounts.index')]]" />
    </x-slot>

    <div class="py-12">
        <div class="sm:px-6 lg:px-8">
            <x-form-card :action="route('cash-accounts.store')" submit="Add Account" :cancel="route('cash-accounts.index')">
                @include('cash-accounts._form')
            </x-form-card>
        </div>
    </div>
</x-app-layout>

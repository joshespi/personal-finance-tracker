<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Add Pension" :crumbs="[['Pension', route('pensions.index')]]" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <x-form-card :action="route('pensions.store')" submit="Add Pension" :cancel="route('pensions.index')">
                @include('pensions._form')
            </x-form-card>
        </div>
    </div>
</x-app-layout>

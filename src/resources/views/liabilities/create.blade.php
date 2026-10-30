<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Add Liability" :crumbs="[['Liabilities', route('liabilities.index')]]" />
    </x-slot>

    <div class="py-12">
        <div class="sm:px-6 lg:px-8">
            <x-form-card :action="route('liabilities.store')" submit="Add Liability" :cancel="route('liabilities.index')">
                @include('liabilities._form')
            </x-form-card>
        </div>
    </div>
</x-app-layout>

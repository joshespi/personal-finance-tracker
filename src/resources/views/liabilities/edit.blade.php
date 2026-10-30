<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit Liability" :crumbs="[['Liabilities', route('liabilities.index')], [$liability->name, route('liabilities.show', $liability)]]" />
    </x-slot>

    <div class="py-12">
        <div class="sm:px-6 lg:px-8">
            <x-form-card :action="route('liabilities.update', $liability)" method="PUT" submit="Save" :cancel="route('liabilities.show', $liability)">
                @include('liabilities._form')
            </x-form-card>
        </div>
    </div>
</x-app-layout>

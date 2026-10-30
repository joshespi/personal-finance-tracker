<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit Income Category" :crumbs="[['Income Categories', route('income-categories.index')]]" />
    </x-slot>

    <div class="py-12">
        <div class="sm:px-6 lg:px-8">
            <x-form-card :action="route('income-categories.update', $category)" method="PUT" submit="Save Changes" :cancel="route('income-categories.index')">
                @include('income-categories._form')
            </x-form-card>
        </div>
    </div>
</x-app-layout>

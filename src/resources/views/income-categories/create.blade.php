<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Add Income Category" :crumbs="[['Income Categories', route('income-categories.index')]]" />
    </x-slot>

    <div class="py-12">
        <div class="sm:px-6 lg:px-8">
            <x-form-card :action="route('income-categories.store')" submit="Add Category" :cancel="route('income-categories.index')">
                @include('income-categories._form')
            </x-form-card>
        </div>
    </div>
</x-app-layout>

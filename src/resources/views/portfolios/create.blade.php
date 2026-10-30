<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">New Portfolio</h2>
    </x-slot>

    <div class="py-12">
        <div class="sm:px-6 lg:px-8">
            <x-form-card :action="route('portfolios.store')" submit="Create Portfolio" :cancel="route('portfolios.index')">
                @include('portfolios._form')
            </x-form-card>
        </div>
    </div>
</x-app-layout>

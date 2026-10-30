<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Add Envelope" :crumbs="[['Budget Envelopes', route('envelopes.index')]]" />
    </x-slot>

    <div class="py-12">
        <div class="sm:px-6 lg:px-8">
            <x-form-card :action="route('envelopes.store')" submit="Add Envelope" :cancel="route('envelopes.index')">
                @include('envelopes._form')
            </x-form-card>
        </div>
    </div>
</x-app-layout>

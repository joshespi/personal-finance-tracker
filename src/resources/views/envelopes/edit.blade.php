<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit Envelope" :crumbs="[['Budget Envelopes', route('envelopes.index')], [$envelope->name, route('envelopes.show', $envelope)]]" />
    </x-slot>

    <div class="py-12">
        <div class="sm:px-6 lg:px-8">
            <x-form-card :action="route('envelopes.update', $envelope)" method="PUT" submit="Save" :cancel="route('envelopes.show', $envelope)">
                @include('envelopes._form')
            </x-form-card>
        </div>
    </div>
</x-app-layout>

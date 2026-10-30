<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Add Manual Asset" :crumbs="[[$portfolio->name, route('portfolios.show', $portfolio)]]" />
    </x-slot>

    <div class="py-12">
        <div class="sm:px-6 lg:px-8">
            <x-form-card :action="route('portfolios.manual-assets.store', $portfolio)" submit="Add Asset" :cancel="route('portfolios.manual-assets.index', $portfolio)">
                @include('manual-assets._form')
            </x-form-card>
        </div>
    </div>
</x-app-layout>

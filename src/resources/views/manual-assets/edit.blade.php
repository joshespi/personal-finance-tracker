<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit Manual Asset" :crumbs="[[$portfolio->name, route('portfolios.show', $portfolio)], [$manualAsset->name, route('manual-assets.show', $manualAsset)]]" />
    </x-slot>

    <div class="py-12">
        <div class="sm:px-6 lg:px-8">
            <x-form-card :action="route('manual-assets.update', $manualAsset)" method="PUT" submit="Save Changes" :cancel="route('manual-assets.show', $manualAsset)">
                @include('manual-assets._form')
            </x-form-card>
        </div>
    </div>
</x-app-layout>

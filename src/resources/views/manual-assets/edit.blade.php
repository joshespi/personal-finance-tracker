<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                <a href="{{ route('portfolios.show', $portfolio) }}" class="hover:underline">{{ $portfolio->name }}</a>
                &rsaquo;
                <a href="{{ route('manual-assets.show', $manualAsset) }}" class="hover:underline">{{ $manualAsset->name }}</a>
            </p>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Edit Manual Asset</h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('manual-assets.update', $manualAsset) }}" class="space-y-6">
                    @csrf
                    @method('PUT')
                    @include('manual-assets._form')
                    <div class="flex items-center gap-4">
                        <x-primary-button>Save Changes</x-primary-button>
                        <a href="{{ route('manual-assets.show', $manualAsset) }}"
                           class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

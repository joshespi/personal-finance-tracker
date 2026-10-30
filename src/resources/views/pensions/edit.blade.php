<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    <a href="{{ route('pensions.index') }}" class="hover:underline">Pension</a> &rsaquo; {{ $pension->name }}
                </p>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Edit Pension</h2>
            </div>
            <form method="POST" action="{{ route('pensions.destroy', $pension) }}"
                  onsubmit="return confirm('Delete this pension? This cannot be undone.')">
                @csrf
                @method('DELETE')
                <x-danger-button type="submit">Delete</x-danger-button>
            </form>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <x-form-card :action="route('pensions.update', $pension)" method="PUT" submit="Save" :cancel="route('pensions.index')">
                @include('pensions._form')
            </x-form-card>
        </div>
    </div>
</x-app-layout>

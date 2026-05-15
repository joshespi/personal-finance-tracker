<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                <a href="{{ route('envelopes.index') }}" class="hover:underline">Budget Envelopes</a> &rsaquo;
                <a href="{{ route('envelopes.show', $envelope) }}" class="hover:underline">{{ $envelope->name }}</a>
            </p>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Edit Envelope</h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('envelopes.update', $envelope) }}" class="space-y-6">
                    @csrf
                    @method('PUT')
                    @include('envelopes._form')
                    <div class="flex items-center gap-4">
                        <x-primary-button>Save</x-primary-button>
                        <a href="{{ route('envelopes.show', $envelope) }}"
                           class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

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

                    <div>
                        <x-input-label for="name" value="Name" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                                      :value="old('name', $envelope->name)" required autofocus maxlength="200" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div class="flex flex-wrap gap-4">
                        <div>
                            <x-input-label for="monthly_target" value="Monthly Target (optional)" />
                            <x-text-input id="monthly_target" name="monthly_target" type="number" class="mt-1 block w-40"
                                          :value="old('monthly_target', $envelope->monthly_target)" min="0" step="any" />
                            <x-input-error :messages="$errors->get('monthly_target')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="color" value="Color" />
                            <input id="color" name="color" type="color"
                                   value="{{ old('color', $envelope->color) }}"
                                   class="mt-1 block h-10 w-20 rounded-md border-gray-300 dark:border-gray-600">
                            <x-input-error :messages="$errors->get('color')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="sort_order" value="Sort Order" />
                            <x-text-input id="sort_order" name="sort_order" type="number" class="mt-1 block w-24"
                                          :value="old('sort_order', $envelope->sort_order)" min="0" step="1" />
                            <x-input-error :messages="$errors->get('sort_order')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="notes" value="Notes (optional)" />
                        <textarea id="notes" name="notes"
                                  class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                  rows="3" maxlength="1000">{{ old('notes', $envelope->notes) }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>

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

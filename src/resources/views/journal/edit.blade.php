<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Edit Entry &mdash; {{ $portfolio->name }}
            </h2>
            <a href="{{ route('portfolios.journal.index', $portfolio) }}"
               class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">&larr; Back to journal</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <form method="POST" action="{{ route('portfolios.journal.update', [$portfolio, $entry]) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Title <span class="font-normal">(optional)</span></label>
                            <input type="text" name="title" value="{{ old('title', $entry->title) }}"
                                   class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Date</label>
                            <input type="date" name="entry_date" value="{{ old('entry_date', $entry->entry_date->format('Y-m-d')) }}" required
                                   class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Body</label>
                        <input id="edit-body" type="hidden" name="body" value="{{ old('body', $entry->body) }}">
                        <trix-editor input="edit-body" class="trix-content rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 text-sm min-h-[140px]"></trix-editor>
                        @error('body')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('portfolios.journal.index', $portfolio) }}"
                           class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 hover:underline">Cancel</a>
                        <button type="submit"
                                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>

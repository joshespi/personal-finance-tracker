<x-app-layout>
    @vite('resources/js/editor.js')
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Journal &mdash; {{ $portfolio->name }}
                </h2>
            </div>
            <a href="{{ route('portfolios.show', $portfolio) }}"
               class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">&larr; Back to portfolio</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            @if (session('success'))
                <div class="rounded-md bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 px-4 py-3 text-sm text-green-800 dark:text-green-300">
                    {{ session('success') }}
                </div>
            @endif

            {{-- New entry form --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-base font-semibold text-gray-800 dark:text-gray-100 mb-4">New Entry</h3>

                <form method="POST" action="{{ route('portfolios.journal.store', $portfolio) }}" class="space-y-4">
                    @csrf

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Title <span class="font-normal">(optional)</span></label>
                            <input type="text" name="title" value="{{ old('title') }}"
                                   class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                   placeholder="e.g. Why I bought more MSFT">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Date</label>
                            <input type="date" name="entry_date" value="{{ old('entry_date', now()->format('Y-m-d')) }}" required
                                   class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Body</label>
                        <textarea name="body" class="markdown-editor">{{ old('body') }}</textarea>
                        @error('body')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end">
                        <button type="submit"
                                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
                            Add Entry
                        </button>
                    </div>
                </form>
            </div>

            {{-- Entry list --}}
            @forelse ($entries as $entry)
                <article class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-start justify-between gap-4 mb-3">
                        <div>
                            <time class="text-xs text-gray-400 dark:text-gray-500">
                                {{ $entry->entry_date->format('M j, Y') }}
                            </time>
                            @if ($entry->title)
                                <h4 class="text-base font-semibold text-gray-800 dark:text-gray-100 mt-0.5">
                                    {{ $entry->title }}
                                </h4>
                            @endif
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <a href="{{ route('portfolios.journal.edit', [$portfolio, $entry]) }}"
                               class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('portfolios.journal.destroy', [$portfolio, $entry]) }}"
                                  onsubmit="return confirm('Delete this entry?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-red-500 hover:underline">Delete</button>
                            </form>
                        </div>
                    </div>
                    <div class="prose dark:prose-invert prose-sm max-w-none text-gray-600 dark:text-gray-300">
                        {!! $entry->rendered_body !!}
                    </div>
                </article>
            @empty
                <div class="text-center py-16 text-gray-400 dark:text-gray-500 text-sm">
                    No entries yet. Add your first note above.
                </div>
            @endforelse

        </div>
    </div>
</x-app-layout>

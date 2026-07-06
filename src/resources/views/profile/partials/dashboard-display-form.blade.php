<section id="dashboard-display">
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Dashboard Display</h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Choose which sections and tiles appear on your dashboard. Unchecked items are hidden.
            Items still only appear when there's data for them.
        </p>
    </header>

    <form method="post" action="{{ route('profile.display') }}" class="mt-6 space-y-6">
        @csrf
        @method('PATCH')

        @foreach (\App\Enums\DashboardWidget::grouped() as $group => $widgets)
            <div x-data="{ setAll(v) { $el.querySelectorAll('input[type=checkbox]').forEach(c => c.checked = v) } }">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-1 mb-3">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $group }}</h3>
                    <div class="flex items-center gap-2 text-xs">
                        <button type="button" @click="setAll(true)"
                                class="text-indigo-600 dark:text-indigo-400 hover:underline">All</button>
                        <span class="text-gray-300 dark:text-gray-600">·</span>
                        <button type="button" @click="setAll(false)"
                                class="text-gray-500 dark:text-gray-400 hover:underline">None</button>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2">
                    @foreach ($widgets as $widget)
                        <label class="flex items-center gap-2.5 cursor-pointer">
                            <input type="checkbox" name="widgets[]" value="{{ $widget->value }}"
                                   class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500"
                                   {{ $user->showsWidget($widget) ? 'checked' : '' }}>
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $widget->label() }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach

        @if (session('status') === 'display-updated')
            <p class="text-sm text-green-600 dark:text-green-400">Saved.</p>
        @endif

        <div class="flex items-center gap-4">
            <x-primary-button>Save Display Options</x-primary-button>
        </div>
    </form>
</section>

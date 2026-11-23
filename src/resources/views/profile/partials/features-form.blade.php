<section id="features">
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Features</h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Choose which parts of the app you use. Turning one off hides it everywhere (menu, dashboard,
            emails) but keeps your data, so it's all still there if you turn it back on. Features work
            on their own, and connect up when more than one is on.
        </p>
    </header>

    <form method="post" action="{{ route('profile.features') }}" class="mt-6 space-y-6">
        @csrf
        @method('PATCH')

        <x-feature-picker :selected="array_map(fn ($f) => $f->value, $user->enabledFeatures())" :error="$errors->first('features')" />

        @if (session('status') === 'features-updated')
            <p class="text-sm text-green-600 dark:text-green-400">Saved.</p>
        @endif

        <x-primary-button>Save Features</x-primary-button>
    </form>
</section>

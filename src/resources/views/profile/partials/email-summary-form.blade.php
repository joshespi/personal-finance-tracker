<section id="email-summary">
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Account Summary Email</h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Get a periodic email covering your account activity. Pick one or more cadences and
            which sections to include.
        </p>
    </header>

    <form method="post" action="{{ route('profile.email-summary') }}" class="mt-6 space-y-6">
        @csrf
        @method('PATCH')

        <div>
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 border-b border-gray-100 dark:border-gray-700 pb-1 mb-3">
                Frequency
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-2">
                @foreach (\App\Enums\EmailSummaryFrequency::cases() as $frequency)
                    <label class="flex items-center gap-2.5 cursor-pointer">
                        <input type="checkbox" name="frequencies[]" value="{{ $frequency->value }}"
                               class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500"
                               {{ $user->wantsEmailFrequency($frequency) ? 'checked' : '' }}>
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ $frequency->label() }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div>
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 border-b border-gray-100 dark:border-gray-700 pb-1 mb-3">
                Sections to include
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
                @foreach (\App\Enums\EmailSummarySection::cases() as $section)
                    <label class="flex items-start gap-2.5 cursor-pointer">
                        <input type="checkbox" name="sections[]" value="{{ $section->value }}"
                               class="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500"
                               {{ $user->wantsEmailSummarySection($section) ? 'checked' : '' }}>
                        <div>
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $section->label() }}</span>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $section->description() }}</p>
                        </div>
                    </label>
                @endforeach
            </div>
        </div>

        @if (session('status') === 'email-summary-updated')
            <p class="text-sm text-green-600 dark:text-green-400">Saved.</p>
        @endif

        <div class="flex items-center gap-4">
            <x-primary-button>Save Summary Preferences</x-primary-button>
        </div>
    </form>
</section>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Email Notifications</h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Choose which automated emails you receive.
        </p>
    </header>

    <form method="post" action="{{ route('profile.notifications') }}" class="mt-6 space-y-4">
        @csrf
        @method('PATCH')

        <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" name="notify_scheduled_transactions" value="1"
                   class="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500"
                   {{ $user->notify_scheduled_transactions ? 'checked' : '' }}>
            <div>
                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Scheduled transaction summary</span>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    Daily email when scheduled transactions are automatically recorded.
                </p>
            </div>
        </label>

        @if (session('status') === 'notifications-updated')
            <p class="text-sm text-green-600 dark:text-green-400">Saved.</p>
        @endif

        <div class="flex items-center gap-4">
            <x-primary-button>Save Preferences</x-primary-button>
        </div>
    </form>
</section>

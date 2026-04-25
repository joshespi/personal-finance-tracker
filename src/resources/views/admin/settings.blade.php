<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Admin &rsaquo; Settings</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 space-y-6">

                @if (session('success'))
                    <div class="bg-green-100 dark:bg-green-900/40 border border-green-300 dark:border-green-700 text-green-800 dark:text-green-300 rounded-md px-4 py-3 text-sm">
                        {{ session('success') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
                    @csrf

                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">User Registration</h3>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="hidden" name="registration_open" value="0">
                            <input type="checkbox" name="registration_open" value="1"
                                   class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                   {{ $settings['registration_open'] ? 'checked' : '' }}>
                            <span class="text-sm text-gray-700 dark:text-gray-300">Allow new user registrations</span>
                        </label>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            When disabled, the registration page redirects to login and new accounts cannot be created.
                        </p>
                    </div>

                    <div class="pt-2">
                        <x-primary-button>Save Settings</x-primary-button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>

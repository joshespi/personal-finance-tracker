<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400 text-sm font-medium">
            <a href="{{ route('admin.users.index') }}" class="hover:text-gray-700 dark:hover:text-gray-200">Users</a>
            <span>/</span>
            <span class="text-gray-800 dark:text-gray-200">{{ $user->name }}</span>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 space-y-6">

                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <div>
                        <x-input-label for="name" value="Name" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                                      value="{{ old('name', $user->name) }}" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="email" value="Email" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                                      value="{{ old('email', $user->email) }}" required />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <div class="flex items-center gap-3">
                        <input type="hidden" name="is_admin" value="0">
                        <input id="is_admin" name="is_admin" type="checkbox" value="1"
                               class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500"
                               {{ old('is_admin', $user->is_admin) ? 'checked' : '' }}>
                        <x-input-label for="is_admin" value="Admin" />
                    </div>

                    <div class="flex items-center gap-4 pt-2">
                        <x-primary-button>Save</x-primary-button>
                        <a href="{{ route('admin.users.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200">Cancel</a>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>

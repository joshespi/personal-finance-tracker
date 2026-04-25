<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400 text-sm font-medium">
            <a href="{{ route('admin.users.index') }}" class="hover:text-gray-700 dark:hover:text-gray-200">Users</a>
            <span>/</span>
            <span class="text-gray-800 dark:text-gray-200">{{ $user->name }}</span>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- User info card --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <div class="flex items-start justify-between">
                    <div class="space-y-1">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $user->name }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
                        <div class="flex items-center gap-2 pt-1">
                            @if ($user->is_admin)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 dark:bg-indigo-900/50 text-indigo-800 dark:text-indigo-300">Admin</span>
                            @endif
                            @if ($user->email_verified_at)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300">Verified</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300">Unverified</span>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.users.edit', $user) }}"
                           class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                            Edit
                        </a>
                        @if ($user->id !== Auth::id())
                            <form method="POST" action="{{ route('admin.impersonate', $user) }}">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded-md text-xs font-semibold text-white hover:bg-indigo-500 transition">
                                    Impersonate
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <dl class="mt-4 grid grid-cols-3 gap-4 pt-4 border-t border-gray-100 dark:border-gray-700 text-sm">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Joined</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $user->created_at->format('M j, Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Portfolios</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $user->portfolios_count }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Last verified</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $user->email_verified_at?->format('M j, Y') ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Login history --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Recent Logins</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">When</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">IP Address</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Browser / Device</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($loginHistory as $entry)
                            <tr>
                                <td class="px-6 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap" title="{{ $entry->created_at->toDateTimeString() }}">
                                    {{ $entry->created_at->diffForHumans() }}
                                </td>
                                <td class="px-6 py-3 text-gray-500 dark:text-gray-400 font-mono text-xs">{{ $entry->ip_address ?? '—' }}</td>
                                <td class="px-6 py-3 text-gray-500 dark:text-gray-400 text-xs truncate max-w-xs" title="{{ $entry->user_agent }}">{{ $entry->user_agent ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-6 text-center text-gray-400">No login history yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</x-app-layout>

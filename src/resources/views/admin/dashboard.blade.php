<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Admin</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Stats grid --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                @foreach ([
                    ['Total Users',   $stats['total_users'],       'text-indigo-600 dark:text-indigo-400'],
                    ['Admins',        $stats['admin_count'],        'text-purple-600 dark:text-purple-400'],
                    ['Unverified',    $stats['unverified_count'],   'text-amber-600 dark:text-amber-400'],
                    ['Portfolios',    $stats['total_portfolios'],   'text-green-600 dark:text-green-400'],
                    ['Transactions',  $stats['total_transactions'], 'text-blue-600 dark:text-blue-400'],
                    ['New (7d)',      $stats['new_users_7d'],       'text-teal-600 dark:text-teal-400'],
                    ['New (30d)',     $stats['new_users_30d'],      'text-cyan-600 dark:text-cyan-400'],
                ] as [$label, $value, $color])
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $label }}</p>
                        <p class="mt-1 text-3xl font-bold {{ $color }}">{{ number_format($value) }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Nav cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <a href="{{ route('admin.users.index') }}"
                   class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5 hover:ring-2 hover:ring-indigo-400 transition group">
                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-200 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">Manage Users</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Edit, impersonate, or remove accounts</p>
                </a>
                <a href="{{ route('admin.settings') }}"
                   class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5 hover:ring-2 hover:ring-indigo-400 transition group">
                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-200 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">Settings</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">App-wide configuration and email</p>
                </a>
                <a href="{{ route('admin.activity') }}"
                   class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5 hover:ring-2 hover:ring-indigo-400 transition group">
                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-200 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">Activity Log</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Full filterable event history</p>
                </a>
            </div>

            {{-- Recent signups --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Recent Signups</h3>
                    <a href="{{ route('admin.users.index') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">All users &rarr;</a>
                </div>
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($recentUsers as $user)
                        <li class="px-6 py-3 flex items-center justify-between gap-4">
                            <div class="min-w-0">
                                <a href="{{ route('admin.users.show', $user) }}" class="text-sm font-medium text-gray-900 dark:text-gray-100 hover:underline">{{ $user->name }}</a>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $user->email }}</p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                @if ($user->email_verified_at)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300">Verified</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300">Unverified</span>
                                @endif
                                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $user->created_at->format('M j') }}</span>
                            </div>
                        </li>
                    @empty
                        <li class="px-6 py-8 text-center text-sm text-gray-400">No users yet.</li>
                    @endforelse
                </ul>
            </div>

        </div>
    </div>
</x-app-layout>

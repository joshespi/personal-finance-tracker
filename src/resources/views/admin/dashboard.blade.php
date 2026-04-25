<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Admin &rsaquo; Dashboard</h2>
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

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-5 flex items-center">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Quick links</p>
                        <div class="mt-1 space-y-1">
                            <a href="{{ route('admin.users.index') }}" class="block text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Manage users</a>
                            <a href="{{ route('admin.activity') }}" class="block text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Activity log</a>
                            <a href="{{ route('admin.settings') }}" class="block text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Settings</a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Recent activity --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Recent Activity</h3>
                    <a href="{{ route('admin.activity') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">View all &rarr;</a>
                </div>
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Action</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Detail</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($recentActivity as $log)
                            <tr>
                                <td class="px-6 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $log->created_at->diffForHumans() }}</td>
                                <td class="px-6 py-3 text-gray-700 dark:text-gray-300">
                                    @if ($log->user)
                                        <a href="{{ route('admin.users.show', $log->user) }}" class="hover:underline">{{ $log->user->name }}</a>
                                    @else
                                        <span class="text-gray-400">&mdash;</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-gray-700 dark:text-gray-300">{{ $log->actionLabel() }}</td>
                                <td class="px-6 py-3 text-gray-500 dark:text-gray-400 text-xs font-mono">
                                    @if ($log->metadata)
                                        {{ collect($log->metadata)->map(fn ($v, $k) => "{$k}: {$v}")->implode(', ') }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-gray-400">No activity yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</x-app-layout>

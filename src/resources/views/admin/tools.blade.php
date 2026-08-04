<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Admin &rsaquo; Tools</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 space-y-6">

                @if (session('output'))
                    <div class="bg-gray-900 dark:bg-gray-950 rounded-md px-4 py-3">
                        <pre class="text-xs text-green-400 whitespace-pre-wrap font-mono">{{ session('output') }}</pre>
                    </div>
                @endif

                <div>
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Backfill Portfolio Snapshots</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                        Rebuilds <code>portfolio_snapshots</code> for past dates. Leave dates blank to use the earliest transaction through yesterday.
                        With <em>Skip fetch</em> unchecked, fetching prices is queued and drains automatically in small batches (see below),
                        then writing snapshots a chunk of days at a time. Check <em>Skip fetch</em> to reuse existing price records and run
                        synchronously instead. <em>Dry run</em> always runs synchronously from existing price records only — it never
                        fetches or writes anything.
                    </p>

                    <form method="POST" action="{{ route('admin.tools.backfill-snapshots') }}" class="space-y-4"
                          x-data="{ submitting: false }" @submit="submitting = true">
                        @csrf

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">From</label>
                                <input type="date" name="from" value="{{ old('from') }}"
                                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">To</label>
                                <input type="date" name="to" value="{{ old('to') }}"
                                       class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Portfolio IDs <span class="font-normal">(comma-separated, blank = all)</span></label>
                            <input type="text" name="portfolio" value="{{ old('portfolio') }}" placeholder="e.g. 1,3"
                                   class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            @error('portfolio')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="skip_fetch" value="1" checked
                                       class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Skip fetch <span class="text-xs text-gray-400">(use existing price records)</span></span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="dry_run" value="1"
                                       class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Dry run <span class="text-xs text-gray-400">(preview without writing)</span></span>
                            </label>
                        </div>

                        <div class="pt-2">
                            <button type="submit" :disabled="submitting"
                                    class="inline-flex items-center px-3 py-1.5 bg-gray-800 dark:bg-gray-700 border border-transparent rounded-md text-xs font-semibold text-white hover:bg-gray-700 dark:hover:bg-gray-600 transition disabled:opacity-50 disabled:cursor-not-allowed">
                                <span x-show="!submitting">Run Backfill</span>
                                <span x-show="submitting" x-cloak>Running…</span>
                            </button>
                        </div>
                    </form>
                </div>

                @if ($backfillRequests->isNotEmpty())
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Backfill Queue</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                            Runs that fetch prices are queued rather than run inline, since pulling years of price history can be slow
                            enough to trip a provider rate limit or a proxy timeout. Queued requests drain automatically in small
                            batches via the hourly <code>assets:process-backfill-queue</code> job — fetching prices first, then writing
                            snapshots a chunk of days at a time — until complete.
                        </p>

                        <div class="overflow-x-auto">
                            <table class="min-w-full text-xs">
                                <thead>
                                    <tr class="text-left text-gray-500 dark:text-gray-400">
                                        <th class="pr-4 pb-2">#</th>
                                        <th class="pr-4 pb-2">Range</th>
                                        <th class="pr-4 pb-2">Status</th>
                                        <th class="pr-4 pb-2">Progress</th>
                                        <th class="pb-2">Note</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-700 dark:text-gray-300">
                                    @foreach ($backfillRequests as $req)
                                        <tr class="border-t border-gray-100 dark:border-gray-700">
                                            <td class="pr-4 py-1.5">{{ $req->id }}</td>
                                            <td class="pr-4 py-1.5">{{ $req->from_date->toDateString() }} → {{ $req->to_date->toDateString() }}</td>
                                            <td class="pr-4 py-1.5">{{ $req->statusLabel() }}</td>
                                            <td class="pr-4 py-1.5">{{ $req->progressLabel() }}</td>
                                            <td class="py-1.5 text-gray-500 dark:text-gray-400">{{ $req->last_note }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>
</x-app-layout>

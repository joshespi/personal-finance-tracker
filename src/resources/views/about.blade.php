<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">About</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

            {{-- Why --}}
            <section>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-3">Why this exists</h3>
                <div class="text-gray-600 dark:text-gray-300 space-y-3 text-sm leading-relaxed">
                    <p>
                        I couldn't find a portfolio tracker that was simple but covered all the asset types I actually own.
                        Most apps handle stocks <em>or</em> crypto, sometimes both — and none of them let you track
                        non-standard assets like a property, a card collection, or a vehicle.
                    </p>
                    <p>
                        Everything you own lives on one balance sheet, so your tracker should too — including a real picture
                        of your net worth. This app lets you record anything with a value you want to timestamp and track
                        over time: traditional securities, crypto, and fully custom assets with manual valuations.
                    </p>
                </div>
            </section>

            {{-- Privacy --}}
            <section>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-3">Privacy</h3>
                <div class="text-gray-600 dark:text-gray-300 space-y-3 text-sm leading-relaxed">
                    <p>
                        We only ask for the minimum information needed to create an account — a name, email, and password.
                    </p>
                    <p>
                        All asset data is entered manually by you. Nothing is linked to any brokerage, exchange, or financial
                        institution. Your data exists purely for your own tracking and visualization — it is not sold,
                        shared, or used for any other purpose.
                    </p>
                    <p>
                        This instance is self-hosted, meaning all data stays on this server. There are no third-party
                        analytics scripts or tracking pixels.
                    </p>
                </div>
            </section>

            {{-- What it tracks --}}
            <section>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-3">What you can track</h3>
                <ul class="grid sm:grid-cols-2 gap-2 text-sm text-gray-600 dark:text-gray-300">
                    @foreach ([
                        ['Stocks & ETFs', 'Live prices via Finnhub'],
                        ['Crypto', 'Live prices via CoinGecko'],
                        ['Real estate', 'Manual valuations, timestamped'],
                        ['Vehicles', 'Track depreciation over time'],
                        ['Collectibles', 'Cards, watches, art — anything'],
                        ['Custom assets', 'If it has a value, you can track it'],
                    ] as [$label, $desc])
                        <li class="flex gap-2 bg-white dark:bg-gray-800 rounded-lg px-4 py-3 border border-gray-100 dark:border-gray-700">
                            <span class="font-medium text-gray-800 dark:text-gray-100 shrink-0">{{ $label }}</span>
                            <span class="text-gray-500 dark:text-gray-400">&mdash; {{ $desc }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- Issues & requests --}}
            <section>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-3">Issues &amp; feature requests</h3>
                <div class="text-gray-600 dark:text-gray-300 space-y-3 text-sm">
                    <p>
                        Found a bug or have an idea?
                        <a href="https://github.com/joshespi/portfolio-tracker/issues" target="_blank" rel="noopener"
                           class="text-indigo-600 dark:text-indigo-400 hover:underline">Open an issue on GitHub</a>.
                    </p>
                    <p>For bugs, include what you expected, what happened, and steps to reproduce. Screenshots help.<br>
                    For feature requests, describe the use case — not just the feature.</p>
                </div>
            </section>

            {{-- Self-host --}}
            <section>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-3">Self-host your own instance</h3>
                <p class="text-sm text-gray-600 dark:text-gray-300 mb-3">
                    The full source is on GitHub. It runs on Docker Compose and takes about five minutes to set up.
                </p>
                <a href="https://github.com/joshespi/portfolio-tracker#getting-started" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-2 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd"/>
                    </svg>
                    Getting Started on GitHub
                </a>
            </section>

        </div>
    </div>
</x-app-layout>

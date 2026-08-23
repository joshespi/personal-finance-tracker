<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Portfolio Tracker') }} &mdash; Free, self-hosted personal finance</title>
        <meta name="description" content="Track investments and budget your cash in one self-hosted app. Free forever, no ads, no third-party data sharing.">

        <!-- Dark mode: apply class before paint to avoid flash -->
        <script>
            if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 dark:text-gray-100 antialiased bg-white dark:bg-gray-900">

        {{-- Nav --}}
        <header class="border-b border-gray-100 dark:border-gray-800">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <a href="/" class="flex items-center gap-2">
                    <x-application-logo class="w-8 h-8 fill-current text-indigo-600" />
                    <span class="font-semibold text-gray-800 dark:text-gray-200">{{ config('app.name') }}</span>
                </a>
                <div class="flex items-center gap-4">
                    <a href="{{ route('login') }}" class="text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition">
                        Log in
                    </a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}"
                           class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-md transition">
                            Create free account
                        </a>
                    @endif
                </div>
            </div>
        </header>

        {{-- Hero --}}
        <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-20 sm:pt-24 sm:pb-28 text-center">
            <h1 class="text-4xl sm:text-5xl font-bold tracking-tight text-gray-900 dark:text-white max-w-3xl mx-auto">
                Investing and budgeting, on one balance sheet.
            </h1>
            <p class="mt-6 text-lg text-gray-600 dark:text-gray-300 max-w-2xl mx-auto leading-relaxed">
                Track stocks, crypto, real estate, and anything else you own alongside envelope budgeting for your
                everyday cash &mdash; in a single app that's free, self-hosted, and never sells or shares your data.
            </p>
            <div class="mt-10 flex items-center justify-center gap-4">
                @if (Route::has('register'))
                    <a href="{{ route('register') }}"
                       class="inline-flex items-center px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-md transition">
                        Get started &mdash; it's free
                    </a>
                @endif
                <a href="{{ route('login') }}"
                   class="inline-flex items-center px-6 py-3 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 text-sm font-semibold rounded-md transition">
                    Log in
                </a>
            </div>
            <p class="mt-6 text-xs text-gray-400 dark:text-gray-500">
                Hosted for free on our own servers &mdash; no subscription, no ads, no credit card.
            </p>
        </section>

        {{-- Feature columns --}}
        <section class="bg-gray-50 dark:bg-gray-800 border-y border-gray-100 dark:border-gray-800">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 grid sm:grid-cols-2 gap-10">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Investing</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed mb-4">
                        Log transactions across as many portfolios as you like. Stocks, ETFs, and crypto get live
                        prices; anything else &mdash; real estate, vehicles, collectibles &mdash; gets a manual
                        valuation you update on your own schedule.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                        <li class="flex gap-2"><span class="text-indigo-500">&bull;</span> Live prices for stocks, ETFs, and crypto</li>
                        <li class="flex gap-2"><span class="text-indigo-500">&bull;</span> Manual assets for anything with a value</li>
                        <li class="flex gap-2"><span class="text-indigo-500">&bull;</span> Realized gains &amp; tax reporting</li>
                        <li class="flex gap-2"><span class="text-indigo-500">&bull;</span> Performance snapshots and benchmarks</li>
                    </ul>
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Budgeting</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed mb-4">
                        Assign income to envelopes, track cash accounts, and schedule recurring transactions. A
                        family of planning calculators helps you see where your money is actually going.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                        <li class="flex gap-2"><span class="text-indigo-500">&bull;</span> Envelope budgeting with "ready to assign"</li>
                        <li class="flex gap-2"><span class="text-indigo-500">&bull;</span> Cashflow, spending trends, and debt payoff planning</li>
                        <li class="flex gap-2"><span class="text-indigo-500">&bull;</span> Emergency fund and 50/30/20 rule tracking</li>
                        <li class="flex gap-2"><span class="text-indigo-500">&bull;</span> Retirement &amp; FIRE projections</li>
                    </ul>
                </div>
            </div>
        </section>

        {{-- Free / self-hosted / privacy --}}
        <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="grid sm:grid-cols-3 gap-8 text-center">
                <div>
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Free, always</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                        This instance is hosted for free on our own servers. No subscription tiers, no paywalled
                        features.
                    </p>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Self-hosted</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                        The full source is open on GitHub. Prefer to run your own copy? It's a Docker Compose stack
                        that takes about five minutes to stand up.
                    </p>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Your data stays yours</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                        Nothing is linked to a brokerage or bank. No third-party analytics or tracking pixels &mdash;
                        your numbers are never sold or shared.
                    </p>
                </div>
            </div>
        </section>

        {{-- Final CTA --}}
        <section class="border-t border-gray-100 dark:border-gray-800">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">Ready to see the whole picture?</h2>
                @if (Route::has('register'))
                    <a href="{{ route('register') }}"
                       class="inline-flex items-center px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-md transition">
                        Create your free account
                    </a>
                @endif
            </div>
        </section>

        {{-- Footer --}}
        <footer class="border-t border-gray-100 dark:border-gray-800">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-gray-400 dark:text-gray-500">
                <span>&copy; {{ now()->year }} {{ config('app.name') }}</span>
                <a href="https://github.com/joshespi/portfolio-tracker" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-2 hover:text-gray-600 dark:hover:text-gray-300 transition">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd"/>
                    </svg>
                    Source on GitHub
                </a>
            </div>
        </footer>
    </body>
</html>

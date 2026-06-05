<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Support this Project</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-6">
                <p class="text-gray-600 dark:text-gray-300 text-sm leading-relaxed">
                    This app is free and open to self-host. No ads, no analytics, no third-party connections —
                    your data stays on whatever server runs it and goes nowhere else. If it's saved you money
                    or time, consider tipping. No pressure at all.
                </p>
            </div>

            @if ($btcAddress)
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-6">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">Bitcoin</h3>
                <div class="flex flex-col sm:flex-row items-start gap-6">
                    <div class="shrink-0 bg-white p-2 rounded-lg border border-gray-200 dark:border-gray-600">
                        <canvas id="btc-qr"
                                data-address="{{ $btcAddress }}"
                                width="200" height="200"></canvas>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2 uppercase tracking-wide">BTC address</p>
                        <div x-data="{ copied: false, address: @js($btcAddress) }" class="flex items-start gap-2">
                            <code class="text-xs font-mono break-all text-gray-800 dark:text-gray-200 bg-gray-50 dark:bg-gray-700 rounded px-3 py-2 block flex-1">
                                {{ $btcAddress }}
                            </code>
                            <button @click="navigator.clipboard.writeText(address); copied = true; setTimeout(() => copied = false, 2000)"
                                    class="shrink-0 mt-0.5 inline-flex items-center px-3 py-2 text-xs font-medium rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                                <span x-show="!copied">Copy</span>
                                <span x-show="copied" x-cloak>Copied!</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @endif
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-6">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-3">GitHub Sponsors</h3>
                <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">
                    Sponsor on GitHub for recurring support. GitHub matches contributions for the first year.
                </p>
                <a href="https://github.com/sponsors/joshespi" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-gray-900 dark:bg-gray-700 text-white text-sm font-medium rounded-md hover:bg-gray-800 dark:hover:bg-gray-600 transition">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd"/>
                    </svg>
                    Sponsor on GitHub
                </a>
            </div>

            <p class="text-xs text-center text-gray-400 dark:text-gray-500">
                The app will always be free. Donations just keep the server lights on and the motivation up.
            </p>

        </div>
    </div>

    @vite('resources/js/donate.js')
</x-app-layout>

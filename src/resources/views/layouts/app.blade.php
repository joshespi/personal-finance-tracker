<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#4f46e5">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="Portfolio Tracker">
        <link rel="apple-touch-icon" href="/icons/icon-180.png">

        <title>{{ config('app.name', 'Portfolio Tracker') }}</title>

        <!-- Dark mode: apply class before paint to avoid flash -->
        <script>
            if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        <script>window.__demoMode = @json($demo->isActive());</script>
        {{-- Pages needing window.Chart (legacy report pages) push their @vite tag here,
             so it's a module script ordered before app.js — required since Alpine's
             init() lifecycle (used by debt-payoff/planning) runs on 'alpine:init'/
             DOMContentLoaded, which always fires after deferred module scripts, but
             still only after chartjs.js has had a chance to register window.Chart if
             it's ordered first among them. --}}
        @stack('head-vite')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased bg-slate-100 dark:bg-gray-900">
        <div class="min-h-screen flex"
             x-data="{
                sidebarOpen: false,
                dark: localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)
             }"
             x-init="$watch('dark', val => { document.documentElement.classList.toggle('dark', val); localStorage.setItem('theme', val ? 'dark' : 'light'); })"
             x-effect="document.body.classList.toggle('overflow-hidden', sidebarOpen)">

            @include('layouts.navigation')

            <!-- Right column -->
            <div class="flex-1 flex flex-col min-w-0 lg:ps-64">

                <!-- Mobile top bar -->
                <div class="lg:hidden sticky top-0 z-20 flex items-center gap-2 h-[calc(3.5rem_+_env(safe-area-inset-top))] pt-[env(safe-area-inset-top)] px-4 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                    <button @click="sidebarOpen = true"
                            class="p-2 -ms-2 rounded-md text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                            aria-label="Open menu" aria-controls="app-sidebar" :aria-expanded="sidebarOpen"
                            title="Open menu">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                        </svg>
                    </button>
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 min-w-0">
                        <x-application-logo class="block h-7 w-auto fill-current text-indigo-600" />
                        <span class="font-semibold text-gray-800 dark:text-gray-200 text-sm truncate">{{ config('app.name') }}</span>
                    </a>
                    <div class="ms-auto flex items-center gap-1">
                        @auth
                        <button @click="$dispatch('open-quick-add')"
                                class="p-2 rounded-md text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                title="Quick add">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                        </button>
                        @endauth
                        <button @click="dark = !dark"
                                class="p-2 rounded-md text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                title="Toggle dark mode">
                            <svg x-show="!dark" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                            </svg>
                            <svg x-show="dark" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" style="display:none;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                            </svg>
                        </button>
                    </div>
                </div>

                @if ($demo->isActive())
                    <div class="bg-violet-600 text-white text-sm font-medium px-4 py-2 flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                        <span class="sm:hidden">Demo mode active.</span>
                        <span class="hidden sm:inline">Demo mode — financial data is anonymized.</span>
                        <form method="POST" action="{{ route('demo-mode.toggle') }}">
                            @csrf
                            <button type="submit" class="underline font-semibold hover:text-violet-200">Exit demo mode</button>
                        </form>
                    </div>
                @endif

                @if (session('impersonate_admin_id'))
                    <div class="bg-amber-500 text-amber-950 text-sm font-medium px-4 py-2 flex items-center justify-between">
                        <span>
                            You are impersonating <strong>{{ Auth::user()->name }}</strong> ({{ Auth::user()->email }}).
                            Changes you make will affect this user's account.
                        </span>
                        <form method="POST" action="{{ route('admin.impersonate.stop') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="ml-4 underline font-semibold hover:text-amber-900">Exit &rarr;</button>
                        </form>
                    </div>
                @endif

                <!-- Page Heading -->
                @isset($header)
                    <header class="bg-white dark:bg-gray-800 shadow dark:shadow-gray-700/50">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <!-- Page Content -->
                <main class="flex-1">
                    {{ $slot }}
                </main>

                <footer class="mt-12 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-400 dark:text-gray-500">
                        <span>Portfolio Tracker <span class="text-gray-400 dark:text-gray-500">v{{ config('app.version') }}</span> &mdash; <a href="{{ route('about') }}" class="hover:text-gray-600 dark:hover:text-gray-300 transition">About</a> &middot; <a href="{{ route('donate') }}" class="hover:text-gray-600 dark:hover:text-gray-300 transition">Donate</a></span>
                        <a href="https://github.com/joshespi/portfolio-tracker" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-1.5 hover:text-gray-600 dark:hover:text-gray-300 transition">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd"/>
                            </svg>
                            GitHub
                        </a>
                    </div>
                </footer>
            </div>
        </div>

        @stack('scripts')
        <script>
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/sw.js');
            }
        </script>
        @livewireScripts
    </body>
</html>

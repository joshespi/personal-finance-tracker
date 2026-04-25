<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

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
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-100 dark:bg-gray-900">
        <div class="min-h-screen">
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

            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white dark:bg-gray-800 shadow dark:shadow-gray-700/50">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>

        @stack('scripts')
    </body>
</html>

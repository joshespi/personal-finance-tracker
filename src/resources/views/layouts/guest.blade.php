<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name') }}</title>

        <x-brand-head />

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        {{-- app.js no longer bundles Alpine; Livewire's copy (below) provides it.
             Included here for parity with layouts/app.blade.php so any x-data
             used on guest pages actually initializes. --}}
        @livewireStyles
    </head>
    <body class="font-sans text-gray-900 dark:text-gray-100 antialiased finance-canvas guest-canvas">
        <div class="min-h-screen flex flex-col sm:justify-center items-center px-4 py-8">
            <div>
                <a href="/" class="flex flex-col items-center gap-2">
                    <x-application-logo class="w-12 h-12" />
                    <span class="font-semibold text-gray-700 dark:text-gray-300 text-lg">{{ config('app.name') }}</span>
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-8 sm:px-8 bg-white dark:bg-gray-800 shadow-md dark:shadow-gray-700/50 overflow-hidden rounded-lg">
                {{ $slot }}
            </div>
        </div>
        @livewireScripts
    </body>
</html>

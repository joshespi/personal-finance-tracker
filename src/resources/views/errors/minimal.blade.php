{{-- Shared shell for the HTTP error pages. Deliberately standalone — no auth, session or DB
     access — so a 500 can't break its own error page. Callers pass $code, $heading, $message. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }} · {{ config('app.name') }}</title>
    <script>
        if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
    {{-- Skip the stylesheet if assets haven't been built, so a missing manifest can't turn an error page into a second error. --}}
    @if (file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css'])
    @endif
</head>
<body class="bg-slate-100 dark:bg-gray-900 min-h-screen flex items-center justify-center font-sans antialiased">
    <main class="text-center px-6 max-w-md">
        <p class="text-6xl font-bold text-indigo-600 dark:text-indigo-400">{{ $code }}</p>
        <h1 class="mt-4 text-xl font-semibold text-gray-800 dark:text-gray-200">{{ $heading }}</h1>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $message }}</p>
        <div class="mt-6 flex items-center justify-center gap-3">
            @isset($retry)
                <button onclick="window.location.reload()"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md hover:bg-indigo-500 transition">
                    Try again
                </button>
            @endisset
            <a href="{{ url('/dashboard') }}"
               class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm font-semibold rounded-md hover:bg-white dark:hover:bg-gray-800 transition">
                Back to dashboard
            </a>
        </div>
    </main>
</body>
</html>

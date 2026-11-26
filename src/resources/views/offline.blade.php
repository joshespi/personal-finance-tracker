<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Offline · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="finance-canvas guest-canvas min-h-screen flex items-center justify-center font-sans antialiased">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm text-center mx-4 px-6 py-8">
        <p class="text-5xl mb-4">📡</p>
        <h1 class="text-xl font-semibold text-gray-800 dark:text-gray-200">You're offline</h1>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Check your connection and try again.</p>
        <button onclick="window.location.reload()"
                class="mt-6 inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md hover:bg-indigo-500 transition">
            Retry
        </button>
    </div>
</body>
</html>

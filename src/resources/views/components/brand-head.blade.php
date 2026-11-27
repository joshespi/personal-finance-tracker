<meta name="theme-color" content="#111827">
<link rel="manifest" href="/manifest.json">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
<link rel="icon" href="/favicon.ico" sizes="16x16 32x32 48x48">
<link rel="icon" href="/icons/ledger.svg" type="image/svg+xml" sizes="any">
<link rel="apple-touch-icon" href="/icons/icon-180.png">
<script>
    (() => {
        // Apply before paint to avoid a flash; a saved choice wins, else follow the OS.
        const stored = localStorage.getItem('theme');
        const dark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.classList.toggle('dark', dark);

        // Keep the browser chrome in step with the page (the app layout's toggle fires finance-theme-change).
        const syncThemeColor = () => document.querySelector('meta[name="theme-color"]')
            .setAttribute('content', document.documentElement.classList.contains('dark') ? '#111827' : '#f9fafb');
        syncThemeColor();
        window.addEventListener('finance-theme-change', syncThemeColor);
    })();
</script>

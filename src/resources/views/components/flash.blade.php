{{-- The green "saved" banner for a redirect's ->with('success', …) flash. --}}
@if (session('success'))
    <div {{ $attributes->merge(['class' => 'bg-green-100 dark:bg-green-900/40 border border-green-300 dark:border-green-700 text-green-800 dark:text-green-300 rounded-md px-4 py-3 text-sm']) }}>
        {{ session('success') }}
    </div>
@endif

{{-- The green "saved" banner for a redirect's ->with('success', …) flash, and the amber
     one for ->with('error', …) (a refused action rather than a validation failure). --}}
@if (session('success'))
    <div {{ $attributes->merge(['class' => 'bg-green-100 dark:bg-green-900/40 border border-green-300 dark:border-green-700 text-green-800 dark:text-green-300 rounded-md px-4 py-3 text-sm']) }}>
        {{ session('success') }}
    </div>
@endif
@if (session('error'))
    <div role="alert" {{ $attributes->merge(['class' => 'bg-amber-100 dark:bg-amber-900/40 border border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-300 rounded-md px-4 py-3 text-sm']) }}>
        {{ session('error') }}
    </div>
@endif

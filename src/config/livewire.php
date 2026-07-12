<?php

return [
    // Auto-injection only fires on requests that render a Livewire component, which
    // would skip the two ledger pages' Alpine bundle on non-Livewire pages and load a
    // second, conflicting Alpine instance on the two that do. @livewireStyles/@livewireScripts
    // in layouts/app.blade.php load it unconditionally on every page instead.
    'inject_assets' => false,
];

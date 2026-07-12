@php
    $req = request();
    $tab = $req->query('tab');

    // ── Section active states (drive accordion auto-open) ──────────────────────
    $budgetActive   = $req->routeIs('envelopes.*', 'ready-to-assign', 'income-categories.*', 'income-entries.*', 'liabilities.*');
    $accountsActive = $req->routeIs('cash-accounts.*');
    $investActive   = $req->routeIs('portfolios.*', 'manual-assets.*', 'transactions.*', 'dividends', 'tax.*', 'pensions.*');
    $planActive     = $req->routeIs('analysis', 'planning', 'forecast', 'cashflow', 'spending-trends', 'budget-rule', 'emergency-fund', 'debt-payoff', 'allocator');

    // analysis/planning default to their first tab when no ?tab= is present.
    $isAnalysisTab = fn (string $t) => $req->routeIs('analysis') && ($tab ?? 'cashflow') === $t;
    $isPlanningTab = fn (string $t) => $req->routeIs('planning') && ($tab ?? 'debt-payoff') === $t;

    // ── Cash accounts for the Accounts section (single aggregate query) ────────
    $currentAccountId = optional($req->route('cash_account'))->id;
    $sidebarAccounts = Auth::user()->cashAccounts()
        ->withCurrentBalance()
        ->orderBy('name')
        ->get()
        ->each(fn ($a) => $a->current_balance = (float) ($a->deposits_total ?? 0) - (float) ($a->withdrawals_total ?? 0));

    $accountItems = [];
    foreach ($sidebarAccounts as $a) {
        $accountItems[] = [
            'label'    => $demo->n($a->name),
            'href'     => route('cash-accounts.show', $a),
            'active'   => $currentAccountId === $a->id,
            'meta'     => ($a->current_balance < 0 ? '−$' : '$').$demo->amt(abs($a->current_balance)),
            'negative' => $a->current_balance < 0,
        ];
    }
    $accountItems[] = ['label' => 'All Transactions', 'href' => route('cash-accounts.all'), 'active' => $req->routeIs('cash-accounts.all')];
    $accountItems[] = ['label' => 'Manage accounts', 'href' => route('cash-accounts.index'), 'active' => $req->routeIs('cash-accounts.index', 'cash-accounts.create')];

    // ── Icon paths (Heroicons v2 outline) ─────────────────────────────────────
    $icons = [
        'home'     => 'm2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
        'budget'   => 'M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75',
        'accounts' => 'M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z',
        'invest'   => 'M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941',
        'plan'     => 'M15.75 15.75V18m-7.5-6.75h.008v.008H8.25v-.008Zm0 2.25h.008v.008H8.25V13.5Zm0 2.25h.008v.008H8.25v-.008Zm0 2.25h.008v.008H8.25V18Zm2.498-6.75h.007v.008h-.007v-.008Zm0 2.25h.007v.008h-.007V13.5Zm0 2.25h.007v.008h-.007v-.008Zm0 2.25h.007v.008h-.007V18Zm2.504-6.75h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V13.5Zm0 2.25h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V18Zm2.498-6.75h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V13.5ZM8.25 6h7.5v2.25h-7.5V6ZM12 2.25c-1.892 0-3.758.11-5.593.322C5.307 2.7 4.5 3.65 4.5 4.757V19.5a2.25 2.25 0 0 0 2.25 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25V4.757c0-1.108-.806-2.057-1.907-2.185A48.507 48.507 0 0 0 12 2.25Z',
        'admin'    => 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z',
        'plus'     => 'M12 4.5v15m7.5-7.5h-15',
        'moon'     => 'M21.752 15.002A9.718 9.718 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z',
        'sun'      => 'M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z',
    ];

    // Reusable section blocks: key => [label, icon, active, items[]].
    $sections = [
        'budget' => [
            'label'  => 'Budget',
            'icon'   => $icons['budget'],
            'active' => $budgetActive,
            'items'  => [
                ['label' => 'Envelopes',   'href' => route('envelopes.index'),         'active' => $req->routeIs('envelopes.*', 'ready-to-assign')],
                ['label' => 'Income',      'href' => route('income-categories.index'), 'active' => $req->routeIs('income-categories.*', 'income-entries.*')],
                ['label' => 'Liabilities', 'href' => route('liabilities.index'),        'active' => $req->routeIs('liabilities.*')],
            ],
        ],
        'accounts' => [
            'label'  => 'Accounts',
            'icon'   => $icons['accounts'],
            'active' => $accountsActive,
            'items'  => $accountItems,
        ],
        'invest' => [
            'label'  => 'Invest',
            'icon'   => $icons['invest'],
            'active' => $investActive,
            'items'  => [
                ['label' => 'Portfolios',   'href' => route('portfolios.index'),  'active' => $req->routeIs('portfolios.*', 'manual-assets.*')],
                ['label' => 'Transactions', 'href' => route('transactions.all'),  'active' => $req->routeIs('transactions.*')],
                ['label' => 'Dividends',    'href' => route('dividends'),         'active' => $req->routeIs('dividends')],
                ['label' => 'Tax',          'href' => route('tax.summary'),       'active' => $req->routeIs('tax.*')],
                ['label' => 'Pension',      'href' => route('pensions.index'),    'active' => $req->routeIs('pensions.*')],
            ],
        ],
        'plan' => [
            'label'  => 'Plan',
            'icon'   => $icons['plan'],
            'active' => $planActive,
            'items'  => [
                ['label' => 'Cashflow',        'href' => route('analysis', ['tab' => 'cashflow']),         'active' => $isAnalysisTab('cashflow') || $req->routeIs('cashflow')],
                ['label' => 'Spending Trends', 'href' => route('analysis', ['tab' => 'trends']),           'active' => $isAnalysisTab('trends') || $req->routeIs('spending-trends')],
                ['label' => '50/30/20 Rule',   'href' => route('analysis', ['tab' => 'budget-rule']),      'active' => $isAnalysisTab('budget-rule') || $req->routeIs('budget-rule')],
                ['label' => 'Emergency Fund',  'href' => route('planning', ['tab' => 'emergency-fund']),   'active' => $isPlanningTab('emergency-fund') || $req->routeIs('emergency-fund')],
                ['label' => 'Debt Payoff',     'href' => route('planning', ['tab' => 'debt-payoff']),      'active' => $isPlanningTab('debt-payoff') || $req->routeIs('debt-payoff')],
                ['label' => 'Allocator',       'href' => route('planning', ['tab' => 'allocator']),        'active' => $isPlanningTab('allocator') || $req->routeIs('allocator')],
                ['label' => 'Retirement',      'href' => route('planning', ['tab' => 'retirement']),       'active' => $isPlanningTab('retirement')],
                ['label' => 'Forecast',        'href' => route('forecast'),                                'active' => $req->routeIs('forecast')],
            ],
        ],
    ];
@endphp

{{-- Mobile backdrop --}}
<div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
     x-transition:enter="transition-opacity ease-linear duration-200"
     x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity ease-linear duration-200"
     x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-30 bg-black/40 lg:hidden"></div>

{{-- Sidebar --}}
<aside id="app-sidebar" x-data="{ open: {
            budget: {{ $budgetActive ? 'true' : 'false' }},
            accounts: {{ $accountsActive ? 'true' : 'false' }},
            invest: {{ $investActive ? 'true' : 'false' }},
            plan: {{ $planActive ? 'true' : 'false' }},
        } }"
       :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
       class="fixed inset-y-0 left-0 z-40 w-64 flex flex-col bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 transform transition-transform duration-200 lg:translate-x-0 pt-[env(safe-area-inset-top)] pb-[env(safe-area-inset-bottom)] ps-[env(safe-area-inset-left)]">

    {{-- Brand --}}
    <div class="h-16 shrink-0 flex items-center gap-2 px-4 border-b border-gray-100 dark:border-gray-700">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2 min-w-0">
            <x-application-logo class="block h-8 w-auto fill-current text-indigo-600" />
            <span class="font-semibold text-gray-800 dark:text-gray-200 text-sm truncate">{{ config('app.name') }}</span>
        </a>
        <button @click="sidebarOpen = false"
                class="ms-auto lg:hidden p-1.5 rounded-md text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                title="Close menu">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    {{-- Scrollable nav --}}
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
        {{-- Dashboard --}}
        <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['home'] }}" />
            </svg>
            <span class="flex-1 truncate">{{ __('Dashboard') }}</span>
        </x-sidebar-link>

        {{-- Collapsible sections --}}
        @foreach ($sections as $key => $section)
            <div class="pt-1">
                <button type="button" @click="open['{{ $key }}'] = !open['{{ $key }}']"
                        :aria-expanded="open['{{ $key }}']"
                        class="w-full flex items-center gap-3 rounded-md px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $section['icon'] }}" />
                    </svg>
                    <span class="flex-1 text-start">{{ $section['label'] }}</span>
                    <svg class="h-4 w-4 shrink-0 transition-transform duration-200" :class="open['{{ $key }}'] && 'rotate-90'"
                         fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </button>

                <div x-show="open['{{ $key }}']" @unless($section['active']) style="display:none" @endunless
                     class="mt-0.5 mb-1 ms-4 ps-3 border-s border-gray-100 dark:border-gray-700 space-y-0.5">
                    @foreach ($section['items'] as $item)
                        <x-sidebar-link :href="$item['href']" :active="$item['active']">
                            <span class="flex-1 truncate">{{ $item['label'] }}</span>
                            @isset($item['meta'])
                                <span class="ms-2 shrink-0 text-xs font-mono {{ ($item['negative'] ?? false) ? 'text-red-600 dark:text-red-400' : ($item['active'] ? 'text-indigo-500 dark:text-indigo-300' : 'text-gray-400 dark:text-gray-500') }}">{{ $item['meta'] }}</span>
                            @endisset
                        </x-sidebar-link>
                    @endforeach
                </div>
            </div>
        @endforeach

        {{-- Admin (standalone) --}}
        @if (Auth::user()->is_admin)
            <div class="pt-1">
                <x-sidebar-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.*')">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['admin'] }}" />
                    </svg>
                    <span class="flex-1 truncate">{{ __('Admin') }}</span>
                </x-sidebar-link>
            </div>
        @endif
    </nav>

    {{-- Pinned footer: quick add, theme, user menu --}}
    <div class="shrink-0 border-t border-gray-100 dark:border-gray-700 p-3 space-y-1">
        @auth
        <button @click="$dispatch('open-quick-add')"
                class="w-full flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-gray-100 transition">
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['plus'] }}" />
            </svg>
            <span class="flex-1 text-start">{{ __('Quick add') }}</span>
        </button>
        @endauth

        <button @click="dark = !dark"
                class="w-full flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-gray-100 transition">
            <svg x-show="!dark" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['moon'] }}" />
            </svg>
            <svg x-show="dark" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true" style="display:none;">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons['sun'] }}" />
            </svg>
            <span class="flex-1 text-start" x-text="dark ? '{{ __('Light mode') }}' : '{{ __('Dark mode') }}'"></span>
        </button>

        {{-- User menu (opens upward) --}}
        <div class="relative" x-data="{ userMenu: false }" @click.outside="userMenu = false" @keydown.escape="userMenu = false">
            <button @click="userMenu = !userMenu"
                    class="w-full flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                <span class="h-8 w-8 shrink-0 rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 flex items-center justify-center text-xs font-semibold">
                    {{ strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}
                </span>
                <span class="flex-1 min-w-0 text-start truncate">{{ Auth::user()->name }}</span>
                <svg class="h-4 w-4 shrink-0 transition-transform duration-200" :class="userMenu && 'rotate-180'"
                     fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" />
                </svg>
            </button>

            <div x-show="userMenu" x-cloak
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 translate-y-1"
                 class="absolute bottom-full inset-x-0 mb-2 rounded-md border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg py-1 z-50">
                <x-sidebar-link :href="route('profile.edit')" :active="request()->routeIs('profile.*')">
                    <span class="flex-1 truncate">{{ __('Profile') }}</span>
                </x-sidebar-link>
                <x-sidebar-link :href="route('import.csv')" :active="request()->routeIs('import.*')">
                    <span class="flex-1 truncate">{{ __('Import CSV') }}</span>
                </x-sidebar-link>
                <x-sidebar-link :href="route('export.index')" :active="request()->routeIs('export.*')">
                    <span class="flex-1 truncate">{{ __('Export & Backup') }}</span>
                </x-sidebar-link>
                <x-sidebar-link :href="route('donate')" :active="request()->routeIs('donate')">
                    <span class="flex-1 truncate">{{ __('Donate') }}</span>
                </x-sidebar-link>
                <form method="POST" action="{{ route('demo-mode.toggle') }}">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-start text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-gray-100 transition">
                        <span class="flex-1 truncate">{{ $demo->isActive() ? __('Exit Demo Mode') : __('Demo Mode') }}</span>
                    </button>
                </form>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-start text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-gray-100 transition">
                        <span class="flex-1 truncate">{{ __('Log Out') }}</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</aside>

@auth
@php
    $qaAccounts         = $sidebarAccounts; // already loaded above (id/name is all quick-add needs)
    $qaEnvelopes        = Auth::user()->envelopes()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
    $qaPortfolios       = Auth::user()->portfolios()->orderBy('name')->get(['id', 'name']);
    $qaIncomeCategories = Auth::user()->incomeCategories()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
    $qaToday            = now()->format('Y-m-d');
@endphp
<div x-data="{
        open: false,
        tab: 'cash',
        cashAccount: {{ $qaAccounts->first()?->id ?? 'null' }},
        cashType: 'withdrawal',
        envelope: {{ $qaEnvelopes->first()?->id ?? 'null' }},
        portfolio: {{ $qaPortfolios->first()?->id ?? 'null' }},
        get cashAction() { return this.cashAccount ? `/cash-accounts/${this.cashAccount}/transactions` : '#'; },
        get fundAction() { return this.envelope ? `/envelopes/${this.envelope}/transactions` : '#'; },
        get buyAction()  { return this.portfolio ? `/portfolios/${this.portfolio}/transactions` : '#'; },
    }"
    @open-quick-add.window="open = true; tab = 'cash'"
    @keydown.escape.window="open = false">

    <div x-show="open" x-cloak @click="open = false"
         class="fixed inset-0 z-40 bg-black/40" style="display:none;"></div>

    <div x-show="open" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none;">
        <div @click.stop class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md">

            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Quick Add</h3>
                <button @click="open = false" aria-label="Close" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="flex border-b border-gray-100 dark:border-gray-700 px-5">
                <button @click="tab = 'cash'"
                        :class="tab === 'cash' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                        class="py-3 px-1 mr-6 text-sm font-medium border-b-2 -mb-px transition">Cash</button>
                <button @click="tab = 'fund'"
                        :class="tab === 'fund' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                        class="py-3 px-1 mr-6 text-sm font-medium border-b-2 -mb-px transition">Fund Envelope</button>
                <button @click="tab = 'buy'"
                        :class="tab === 'buy' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                        class="py-3 px-1 text-sm font-medium border-b-2 -mb-px transition">Log Buy</button>
            </div>

            <div class="p-5">

                {{-- Cash Tab --}}
                <div x-show="tab === 'cash'" style="display:none;">
                    @if ($qaAccounts->isNotEmpty())
                        <form :action="cashAction" method="POST" class="space-y-4">
                            @csrf
                            <input type="hidden" name="type" :value="cashType">
                            <div class="flex gap-2">
                                <button type="button" @click="cashType = 'withdrawal'"
                                        :class="cashType === 'withdrawal' ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300 border-red-300 dark:border-red-700' : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 border-gray-300 dark:border-gray-600'"
                                        class="flex-1 py-2 text-sm font-medium border rounded-md transition">Withdrawal</button>
                                <button type="button" @click="cashType = 'deposit'"
                                        :class="cashType === 'deposit' ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300 border-green-300 dark:border-green-700' : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 border-gray-300 dark:border-gray-600'"
                                        class="flex-1 py-2 text-sm font-medium border rounded-md transition">Deposit</button>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Account</label>
                                <select x-model="cashAccount" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    @foreach ($qaAccounts as $a)
                                        <option value="{{ $a->id }}">{{ $a->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Amount</label>
                                <input type="number" name="amount" step="0.01" min="0.01" required placeholder="0.00"
                                       class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Description</label>
                                <input type="text" name="description" maxlength="500" placeholder="Optional"
                                       class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            @if ($qaIncomeCategories->isNotEmpty())
                                <div x-show="cashType === 'deposit'" x-cloak>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Income category</label>
                                    <select name="income_category_id" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="">— Uncategorized —</option>
                                        @foreach ($qaIncomeCategories as $cat)
                                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Date</label>
                                <input type="date" name="occurred_at" required value="{{ $qaToday }}"
                                       class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <button type="submit" class="w-full py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-md transition">Record</button>
                        </form>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400 py-2">
                            No spending accounts yet.
                            <a href="{{ route('cash-accounts.create') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Create one</a> first.
                        </p>
                    @endif
                </div>

                {{-- Fund Envelope Tab --}}
                <div x-show="tab === 'fund'" style="display:none;">
                    @if ($qaEnvelopes->isNotEmpty())
                        <form :action="fundAction" method="POST" class="space-y-4">
                            @csrf
                            <input type="hidden" name="type" value="fund">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Envelope</label>
                                <select x-model="envelope" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    @foreach ($qaEnvelopes as $e)
                                        <option value="{{ $e->id }}">{{ $e->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Amount</label>
                                <input type="number" name="amount" step="0.01" min="0.01" required placeholder="0.00"
                                       class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Date</label>
                                <input type="date" name="occurred_at" required value="{{ $qaToday }}"
                                       class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <button type="submit" class="w-full py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-md transition">Fund</button>
                        </form>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400 py-2">
                            No envelopes yet.
                            <a href="{{ route('envelopes.create') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Create one</a> first.
                        </p>
                    @endif
                </div>

                {{-- Log Buy Tab --}}
                <div x-show="tab === 'buy'" style="display:none;">
                    @if ($qaPortfolios->isNotEmpty())
                        <form :action="buyAction" method="POST" class="space-y-4">
                            @csrf
                            <input type="hidden" name="type" value="buy">
                            <input type="hidden" name="currency" value="USD">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Portfolio</label>
                                <select x-model="portfolio" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    @foreach ($qaPortfolios as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex gap-3">
                                <div class="flex-1">
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Symbol</label>
                                    <input type="text" name="symbol" maxlength="20" required placeholder="AAPL"
                                           class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md text-sm uppercase focus:ring-indigo-500 focus:border-indigo-500">
                                </div>
                                <div class="w-28">
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Asset type</label>
                                    <select name="asset_type" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                        @foreach (\App\Enums\AssetType::cases() as $type)
                                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <div class="flex-1">
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Quantity</label>
                                    <input type="number" name="quantity" step="any" min="0.000001" required placeholder="10"
                                           class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                </div>
                                <div class="flex-1">
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Price / unit</label>
                                    <input type="number" name="price_per_unit" step="any" min="0" required placeholder="150.00"
                                           class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Date</label>
                                <input type="date" name="transacted_at" required value="{{ $qaToday }}"
                                       class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <button type="submit" class="w-full py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-md transition">Log Buy</button>
                        </form>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400 py-2">
                            No portfolios yet.
                            <a href="{{ route('portfolios.create') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Create one</a> first.
                        </p>
                    @endif
                </div>

            </div>
        </div>
    </div>
</div>
@endauth

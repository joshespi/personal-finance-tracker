<nav x-data="{
        open: false,
        dark: localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)
    }"
    x-init="$watch('dark', val => { document.documentElement.classList.toggle('dark', val); localStorage.setItem('theme', val ? 'dark' : 'light'); })"
    class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                        <x-application-logo class="block h-8 w-auto fill-current text-indigo-600" />
                        <span class="font-semibold text-gray-800 dark:text-gray-200 text-sm hidden sm:block">{{ config('app.name') }}</span>
                    </a>
                </div>
                @php
                    $portfoliosActive = request()->routeIs('portfolios.*')
                        || request()->routeIs('manual-assets.*')
                        || request()->routeIs('transactions.*')
                        || request()->routeIs('watchlist.*')
                        || request()->routeIs('tax.*')
                        || request()->routeIs('dividends');
                    $moneyActive = request()->routeIs('cash-accounts.*')
                        || request()->routeIs('envelopes.*')
                        || request()->routeIs('liabilities.*')
                        || request()->routeIs('cashflow')
                        || request()->routeIs('spending-trends')
                        || request()->routeIs('emergency-fund')
                        || request()->routeIs('budget-rule')
                        || request()->routeIs('debt-payoff')
                        || request()->routeIs('allocator')
                        || request()->routeIs('ready-to-assign')
                        || request()->routeIs('income-entries.*')
                        || request()->routeIs('scheduled-transactions.*');

                    $triggerActive = 'inline-flex items-center px-1 pt-1 border-b-2 border-indigo-400 text-sm font-medium leading-5 text-gray-900 dark:text-gray-100 focus:outline-none transition duration-150 ease-in-out';
                    $triggerInactive = 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 dark:hover:border-gray-600 focus:outline-none transition duration-150 ease-in-out';
                @endphp
                <div class="hidden sm:space-x-4 lg:space-x-8 sm:-my-px sm:ms-4 lg:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>

                    <div class="relative flex items-stretch" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
                        <button type="button" @click="open = !open" class="{{ $portfoliosActive ? $triggerActive : $triggerInactive }}">
                            {{ __('Portfolios') }}
                            <svg class="ms-1 h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                        <div x-show="open"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute z-50 top-full start-0 mt-1 w-48 rounded-md shadow-lg ltr:origin-top-left rtl:origin-top-right"
                             style="display: none;"
                             @click="open = false">
                            <div class="rounded-md ring-1 ring-black ring-opacity-5 dark:ring-gray-700 py-1 bg-white dark:bg-gray-800">
                                <x-dropdown-link :href="route('portfolios.index')">{{ __('Portfolios') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('transactions.all')">{{ __('Transactions') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('dividends')">{{ __('Dividend Income') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('watchlist.index')">{{ __('Watchlist') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('tax.summary')">{{ __('Tax Summary') }}</x-dropdown-link>
                            </div>
                        </div>
                    </div>

                    <div class="relative flex items-stretch" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
                        <button type="button" @click="open = !open" class="{{ $moneyActive ? $triggerActive : $triggerInactive }}">
                            {{ __('Money') }}
                            <svg class="ms-1 h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                        <div x-show="open"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute z-50 top-full start-0 mt-1 w-48 rounded-md shadow-lg ltr:origin-top-left rtl:origin-top-right"
                             style="display: none;"
                             @click="open = false">
                            <div class="rounded-md ring-1 ring-black ring-opacity-5 dark:ring-gray-700 py-1 bg-white dark:bg-gray-800">
                                <x-dropdown-link :href="route('ready-to-assign')">{{ __('Ready to Assign') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('cashflow')">{{ __('Cashflow') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('spending-trends')">{{ __('Spending Trends') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('emergency-fund')">{{ __('Emergency Fund') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('debt-payoff')">{{ __('Debt Payoff') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('allocator')">{{ __('Extra-Cash Allocator') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('budget-rule')">{{ __('50/30/20 Rule') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('cash-accounts.index')">{{ __('Spending Accounts') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('envelopes.index')">{{ __('Budget Envelopes') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('scheduled-transactions.index')">{{ __('Scheduled') }}</x-dropdown-link>
                                <x-dropdown-link :href="route('liabilities.index')">{{ __('Liabilities') }}</x-dropdown-link>
                            </div>
                        </div>
                    </div>

                    <x-nav-link :href="route('forecast')" :active="request()->routeIs('forecast')">
                        {{ __('Forecast') }}
                    </x-nav-link>

                    @if (Auth::user()->is_admin)
                        <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.*')">
                            {{ __('Admin') }}
                        </x-nav-link>
                    @endif
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6 gap-2">
                <!-- Quick add -->
                @auth
                <button @click="$dispatch('open-quick-add')"
                        class="p-2 rounded-md text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                        title="Quick add">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </button>
                @endauth
                <!-- Theme toggle -->
                <button @click="dark = !dark"
                        class="p-2 rounded-md text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                        title="Toggle dark mode">
                    <!-- Moon: show in light mode to switch to dark -->
                    <svg x-show="!dark" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                    <!-- Sun: show in dark mode to switch to light -->
                    <svg x-show="dark" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </button>

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-200 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>
                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('export.index')">
                            {{ __('Export & Backup') }}
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('donate')">
                            {{ __('Donate') }}
                        </x-dropdown-link>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault(); this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="-me-2 flex items-center sm:hidden gap-1">
                <!-- Mobile quick add -->
                @auth
                <button @click="$dispatch('open-quick-add')"
                        class="p-2 rounded-md text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                        title="Quick add">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </button>
                @endauth
                <!-- Mobile theme toggle -->
                <button @click="dark = !dark"
                        class="p-2 rounded-md text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                    <svg x-show="!dark" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                    <svg x-show="dark" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </button>
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-700 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>

            <p class="px-4 pt-3 pb-1 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ __('Portfolios') }}</p>
            <x-responsive-nav-link :href="route('portfolios.index')" :active="request()->routeIs('portfolios.*') || request()->routeIs('manual-assets.*')">
                {{ __('Portfolios') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('transactions.all')" :active="request()->routeIs('transactions.all')">
                {{ __('Transactions') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('dividends')" :active="request()->routeIs('dividends')">
                {{ __('Dividend Income') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('watchlist.index')" :active="request()->routeIs('watchlist.*')">
                {{ __('Watchlist') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('tax.summary')" :active="request()->routeIs('tax.*')">
                {{ __('Tax Summary') }}
            </x-responsive-nav-link>

            <p class="px-4 pt-3 pb-1 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ __('Money') }}</p>
            <x-responsive-nav-link :href="route('ready-to-assign')" :active="request()->routeIs('ready-to-assign')">
                {{ __('Ready to Assign') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('cashflow')" :active="request()->routeIs('cashflow')">
                {{ __('Cashflow') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('spending-trends')" :active="request()->routeIs('spending-trends')">
                {{ __('Spending Trends') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('emergency-fund')" :active="request()->routeIs('emergency-fund')">
                {{ __('Emergency Fund') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('debt-payoff')" :active="request()->routeIs('debt-payoff')">
                {{ __('Debt Payoff') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('allocator')" :active="request()->routeIs('allocator')">
                {{ __('Extra-Cash Allocator') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('budget-rule')" :active="request()->routeIs('budget-rule')">
                {{ __('50/30/20 Rule') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('cash-accounts.index')" :active="request()->routeIs('cash-accounts.*')">
                {{ __('Spending Accounts') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('envelopes.index')" :active="request()->routeIs('envelopes.*')">
                {{ __('Budget Envelopes') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('scheduled-transactions.index')" :active="request()->routeIs('scheduled-transactions.*')">
                {{ __('Scheduled') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('liabilities.index')" :active="request()->routeIs('liabilities.*')">
                {{ __('Liabilities') }}
            </x-responsive-nav-link>

            <p class="px-4 pt-3 pb-1 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ __('Planning') }}</p>
            <x-responsive-nav-link :href="route('forecast')" :active="request()->routeIs('forecast')">
                {{ __('Forecast') }}
            </x-responsive-nav-link>

            @if (Auth::user()->is_admin)
                <p class="px-4 pt-3 pb-1 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ __('Admin') }}</p>
                <x-responsive-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.*')">
                    {{ __('Admin') }}
                </x-responsive-nav-link>
            @endif
        </div>
        <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-700">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800 dark:text-gray-200">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500 dark:text-gray-400">{{ Auth::user()->email }}</div>
            </div>
            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('export.index')" :active="request()->routeIs('export.*')">
                    {{ __('Export & Backup') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('donate')" :active="request()->routeIs('donate')">
                    {{ __('Donate') }}
                </x-responsive-nav-link>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault(); this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>

@auth
@php
    $qaAccounts   = Auth::user()->cashAccounts()->orderBy('name')->get(['id', 'name']);
    $qaEnvelopes  = Auth::user()->envelopes()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
    $qaPortfolios = Auth::user()->portfolios()->orderBy('name')->get(['id', 'name']);
    $qaToday      = now()->format('Y-m-d');
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
                <button @click="open = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition">
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
                                        <option value="stock">Stock</option>
                                        <option value="crypto">Crypto</option>
                                        <option value="bond">Bond</option>
                                        <option value="real_estate">Real Estate</option>
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

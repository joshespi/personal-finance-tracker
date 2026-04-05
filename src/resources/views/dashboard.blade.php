<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
            <a href="{{ route('portfolios.create') }}"
               class="inline-flex items-center px-3 py-1.5 bg-gray-800 border border-transparent rounded-md text-xs font-semibold text-white hover:bg-gray-700 transition">
                + New Portfolio
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            @if ($summaries->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-8 text-center">
                    <p class="text-gray-500 mb-4">No portfolios yet.</p>
                    <a href="{{ route('portfolios.create') }}"
                       class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md text-sm font-semibold text-white hover:bg-gray-700 transition">
                        Create your first portfolio
                    </a>
                </div>
            @else

                {{-- Top-level totals --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-white shadow-sm sm:rounded-lg px-5 py-4">
                        <p class="text-xs text-gray-500 uppercase tracking-wide">Total Cost Basis</p>
                        <p class="mt-1 text-2xl font-semibold font-mono text-gray-900">
                            ${{ number_format($totals['cost_basis'], 2) }}
                        </p>
                    </div>

                    @if ($totals['market_value'] !== null)
                        <div class="bg-white shadow-sm sm:rounded-lg px-5 py-4">
                            <p class="text-xs text-gray-500 uppercase tracking-wide">Market Value</p>
                            <p class="mt-1 text-2xl font-semibold font-mono text-gray-900">
                                ${{ number_format($totals['market_value'], 2) }}
                            </p>
                        </div>

                        <div class="bg-white shadow-sm sm:rounded-lg px-5 py-4">
                            <p class="text-xs text-gray-500 uppercase tracking-wide">Unrealized P&L</p>
                            @php $unr = $totals['unrealized'] ?? 0; @endphp
                            <p class="mt-1 text-2xl font-semibold font-mono {{ $unr >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $unr >= 0 ? '+' : '' }}${{ number_format($unr, 2) }}
                            </p>
                        </div>
                    @endif

                    @if ($totals['manual_value'] > 0)
                        <div class="bg-white shadow-sm sm:rounded-lg px-5 py-4">
                            <p class="text-xs text-gray-500 uppercase tracking-wide">Manual Assets</p>
                            <p class="mt-1 text-2xl font-semibold font-mono text-gray-900">
                                ${{ number_format($totals['manual_value'], 2) }}
                            </p>
                        </div>
                    @endif
                </div>

                {{-- Per-portfolio breakdown --}}
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="text-base font-semibold text-gray-900">Portfolios</h3>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach ($summaries as $s)
                            <div class="px-6 py-4 flex items-center justify-between hover:bg-gray-50">
                                <div>
                                    <a href="{{ route('portfolios.show', $s['portfolio']) }}"
                                       class="font-medium text-gray-900 hover:underline">
                                        {{ $s['portfolio']->name }}
                                    </a>
                                    @if ($s['portfolio']->description)
                                        <p class="text-xs text-gray-400 mt-0.5">{{ $s['portfolio']->description }}</p>
                                    @endif
                                </div>
                                <div class="flex items-center gap-8 text-right text-sm shrink-0 ms-4">
                                    <div>
                                        <p class="text-xs text-gray-400">Cost Basis</p>
                                        <p class="font-mono text-gray-700">${{ number_format($s['cost_basis'], 2) }}</p>
                                    </div>
                                    @if ($s['market_value'] !== null)
                                        <div>
                                            <p class="text-xs text-gray-400">Market Value</p>
                                            <p class="font-mono text-gray-900 font-semibold">${{ number_format($s['market_value'], 2) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-400">P&L</p>
                                            @php $unr = $s['unrealized'] ?? 0; @endphp
                                            <p class="font-mono font-semibold {{ $unr >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                {{ $unr >= 0 ? '+' : '' }}${{ number_format($unr, 2) }}
                                            </p>
                                        </div>
                                    @endif
                                    @if ($s['manual_value'] > 0)
                                        <div>
                                            <p class="text-xs text-gray-400">Manual</p>
                                            <p class="font-mono text-gray-700">${{ number_format($s['manual_value'], 2) }}</p>
                                        </div>
                                    @endif
                                    <a href="{{ route('portfolios.show', $s['portfolio']) }}"
                                       class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md text-xs font-semibold text-gray-700 hover:bg-gray-50 transition">
                                        View
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

            @endif
        </div>
    </div>
</x-app-layout>

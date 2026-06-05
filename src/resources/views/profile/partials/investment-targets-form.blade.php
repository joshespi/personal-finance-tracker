<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Global Investment Targets</h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Set your target asset-class mix across all portfolios. The dashboard will show how far you've drifted and what to rebalance.
            Leave all at 0 to disable. Values must sum to 100 when non-zero.
        </p>
    </header>

    <form method="post" action="{{ route('profile.targets') }}" class="mt-6 space-y-4"
          x-data="{
              stock: {{ (float) $user->target_stock_pct }},
              crypto: {{ (float) $user->target_crypto_pct }},
              real_estate: {{ (float) $user->target_real_estate_pct }},
              bond: {{ (float) $user->target_bond_pct }},
              get total() {
                  return Math.round((parseFloat(this.stock || 0) + parseFloat(this.crypto || 0) + parseFloat(this.real_estate || 0) + parseFloat(this.bond || 0)) * 100) / 100;
              },
              get totalOk() { return this.total === 0 || this.total === 100; },
          }">
        @csrf
        @method('PATCH')

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div>
                <x-input-label for="target_stock_pct" value="Stocks %" />
                <x-text-input id="target_stock_pct" name="target_stock_pct" type="number"
                              class="mt-1 block w-full"
                              :value="old('target_stock_pct', $user->target_stock_pct)"
                              x-model="stock"
                              min="0" max="100" step="0.5" />
            </div>
            <div>
                <x-input-label for="target_crypto_pct" value="Crypto %" />
                <x-text-input id="target_crypto_pct" name="target_crypto_pct" type="number"
                              class="mt-1 block w-full"
                              :value="old('target_crypto_pct', $user->target_crypto_pct)"
                              x-model="crypto"
                              min="0" max="100" step="0.5" />
            </div>
            <div>
                <x-input-label for="target_real_estate_pct" value="Real Estate %" />
                <x-text-input id="target_real_estate_pct" name="target_real_estate_pct" type="number"
                              class="mt-1 block w-full"
                              :value="old('target_real_estate_pct', $user->target_real_estate_pct)"
                              x-model="real_estate"
                              min="0" max="100" step="0.5" />
            </div>
            <div>
                <x-input-label for="target_bond_pct" value="Bonds %" />
                <x-text-input id="target_bond_pct" name="target_bond_pct" type="number"
                              class="mt-1 block w-full"
                              :value="old('target_bond_pct', $user->target_bond_pct)"
                              x-model="bond"
                              min="0" max="100" step="0.5" />
            </div>
        </div>

        <div class="flex items-center gap-2 text-sm">
            <span class="text-gray-500 dark:text-gray-400">Total:</span>
            <span class="font-mono font-semibold"
                  :class="totalOk ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                  x-text="total + '%'"></span>
            <span x-show="!totalOk && total !== 0"
                  class="text-xs text-red-500 dark:text-red-400"
                  x-text="'(' + (100 - total).toFixed(2).replace(/\.?0+$/, '') + '% remaining)'"></span>
            <span x-show="totalOk && total === 100"
                  class="text-xs text-green-600 dark:text-green-400">✓</span>
        </div>

        @if ($errors->investmentTargets->any())
            <p class="text-sm text-red-600 dark:text-red-400">{{ $errors->investmentTargets->first() }}</p>
        @endif

        @if (session('status') === 'targets-updated')
            <p class="text-sm text-green-600 dark:text-green-400">Saved.</p>
        @endif

        <div class="flex items-center gap-4">
            <x-primary-button>Save Targets</x-primary-button>
        </div>
    </form>
</section>

<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                <a href="{{ route('dashboard') }}" class="hover:underline">Dashboard</a>
            </p>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Transfer Between Portfolios</h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                    Records a linked Transfer Out and Transfer In across two portfolios (e.g. exchange → cold wallet).
                </p>
                <form method="POST" action="{{ route('transfers.store') }}" class="space-y-6"
                      x-data="{
                          qty: {{ old('quantity', 0) }},
                          fees: {{ old('fees', 0) }},
                          feeInAsset: {{ old('fee_in_asset') ? 'true' : 'false' }},
                          get received() {
                              let q = parseFloat(this.qty) || 0;
                              let f = parseFloat(this.fees) || 0;
                              return this.feeInAsset && f > 0 ? Math.max(0, q - f) : q;
                          }
                      }">
                    @csrf

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="from_portfolio_id" value="From Portfolio" />
                            <select id="from_portfolio_id" name="from_portfolio_id" required
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="">Select…</option>
                                @foreach ($portfolios as $p)
                                    <option value="{{ $p->id }}" @selected(old('from_portfolio_id') == $p->id)>{{ $p->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('from_portfolio_id')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="to_portfolio_id" value="To Portfolio" />
                            <select id="to_portfolio_id" name="to_portfolio_id" required
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                <option value="">Select…</option>
                                @foreach ($portfolios as $p)
                                    <option value="{{ $p->id }}" @selected(old('to_portfolio_id') == $p->id)>{{ $p->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('to_portfolio_id')" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="symbol" value="Symbol (e.g. BTC, AAPL)" />
                            <x-text-input id="symbol" name="symbol" type="text" class="mt-1 block w-full"
                                          :value="old('symbol')" required autofocus maxlength="20"
                                          placeholder="BTC" style="text-transform:uppercase" />
                            <x-input-error :messages="$errors->get('symbol')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="asset_type" value="Asset Type" />
                            <select id="asset_type" name="asset_type"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                @foreach (App\Enums\AssetType::cases() as $type)
                                    <option value="{{ $type->value }}" @selected(old('asset_type', 'stock') === $type->value)>{{ $type->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('asset_type')" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <x-input-label for="quantity" value="Quantity Sent" />
                            <x-text-input id="quantity" name="quantity" type="number" class="mt-1 block w-full"
                                          :value="old('quantity')" required min="0.00000001" step="any" placeholder="0.5"
                                          x-model="qty" />
                            <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="price_per_unit" value="Price / Unit" />
                            <x-text-input id="price_per_unit" name="price_per_unit" type="number" class="mt-1 block w-full"
                                          :value="old('price_per_unit', '0')" required min="0" step="any" placeholder="0.00" />
                            <x-input-error :messages="$errors->get('price_per_unit')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="fees" value="Network Fee" />
                            <x-text-input id="fees" name="fees" type="number" class="mt-1 block w-full"
                                          :value="old('fees', '0')" min="0" step="any" placeholder="0.00"
                                          x-model="fees" />
                            <x-input-error :messages="$errors->get('fees')" class="mt-2" />
                        </div>
                    </div>

                    <div x-show="parseFloat(fees) > 0" x-cloak class="space-y-3">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="fee_in_asset" value="1"
                                   x-model="feeInAsset"
                                   class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                Fee paid in the asset (e.g. gas fee deducted from crypto received)
                            </span>
                        </label>

                        <div x-show="feeInAsset" x-cloak
                             class="rounded-md bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 px-3 py-2 text-sm text-amber-800 dark:text-amber-300">
                            Destination will receive
                            <span class="font-mono font-semibold" x-text="received.toFixed(8).replace(/\.?0+$/, '')"></span>
                            (sent <span class="font-mono" x-text="(parseFloat(qty)||0).toFixed(8).replace(/\.?0+$/, '')"></span>
                            − fee <span class="font-mono" x-text="(parseFloat(fees)||0).toFixed(8).replace(/\.?0+$/, '')"></span>)
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="currency" value="Currency" />
                            <x-text-input id="currency" name="currency" type="text" class="mt-1 block w-full"
                                          :value="old('currency', 'USD')" required maxlength="3"
                                          placeholder="USD" style="text-transform:uppercase" />
                            <x-input-error :messages="$errors->get('currency')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="transacted_at" value="Date" />
                            <x-text-input id="transacted_at" name="transacted_at" type="date" class="mt-1 block w-full"
                                          :value="old('transacted_at', now()->format('Y-m-d'))" required />
                            <x-input-error :messages="$errors->get('transacted_at')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="notes" value="Notes (optional)" />
                        <textarea id="notes" name="notes"
                                  class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                  rows="2" maxlength="1000">{{ old('notes') }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>Record Transfer</x-primary-button>
                        <a href="{{ route('dashboard') }}"
                           class="text-sm text-gray-600 dark:text-gray-400 hover:underline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

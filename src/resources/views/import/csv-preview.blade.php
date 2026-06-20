<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">CSV Import — Map Columns</h2>
            <form method="POST" action="{{ route('import.csv.cancel') }}">
                @csrf
                <button type="submit" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                    Cancel import
                </button>
            </form>
        </div>
    </x-slot>

    <div class="py-12" x-data="csvImport({
        headers: @js($headers),
        rows: @js($rows),
        presets: @js($presets),
        detected: @js($detected),
        fields: @js($fields),
    })">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if ($errors->any())
                <div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 rounded-lg px-5 py-4">
                    @foreach ($errors->all() as $error)
                        <p class="text-sm text-red-700 dark:text-red-300">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            {{-- Summary --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg px-6 py-4 flex flex-wrap items-center gap-8">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Rows in file</p>
                    <p class="text-2xl font-semibold font-mono text-gray-900 dark:text-gray-100">{{ number_format(count($rows)) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Columns</p>
                    <p class="text-2xl font-semibold font-mono text-gray-900 dark:text-gray-100">{{ count($headers) }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Preset</span>
                    @foreach ($presets as $key => $preset)
                        <button type="button" @click="applyPreset('{{ $key }}')"
                                class="text-xs px-2.5 py-1 rounded-md border transition"
                                :class="selectedPreset === '{{ $key }}'
                                    ? 'bg-indigo-600 text-white border-indigo-600'
                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 border-gray-300 dark:border-gray-600'">
                            {{ $preset['label'] }}
                        </button>
                    @endforeach
                    <button type="button" @click="clearMapping()"
                            class="text-xs px-2.5 py-1 rounded-md border border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400">
                        Clear
                    </button>
                </div>
            </div>

            <form method="POST" action="{{ route('import.csv.commit') }}" class="space-y-6">
                @csrf

                {{-- Column mapping --}}
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Map columns</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Match each field to a column from your file. Map a single signed <strong>Amount</strong> column,
                            or an <strong>Inflow</strong>/<strong>Outflow</strong> pair (used only when Amount is unmapped).
                        </p>
                    </div>
                    <div class="px-6 py-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <template x-for="field in fields" :key="field.key">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    <span x-text="field.label"></span>
                                    <span x-show="field.required" class="text-red-500">*</span>
                                </label>
                                <select :name="'columns[' + field.key + ']'" x-model="columns[field.key]"
                                        class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">— none —</option>
                                    <template x-for="h in headers" :key="h">
                                        <option :value="h" x-text="h"></option>
                                    </template>
                                </select>
                            </div>
                        </template>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Date format</label>
                            <select name="date_format" x-model="dateFormat"
                                    class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                @foreach (\App\Support\ImportPresets::DATE_FORMATS as $fmt)
                                    <option value="{{ $fmt }}">{{ $fmt }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Account destination --}}
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Destination accounts</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5"
                           x-text="columns.account
                               ? 'Map each account found in the file to an existing account, a new one, or skip it.'
                               : 'No account column mapped — choose one account to import every row into.'"></p>
                    </div>

                    {{-- Per-account mapping (account column present) --}}
                    <div x-show="columns.account" class="divide-y divide-gray-100 dark:divide-gray-700">
                        <template x-for="val in accountValues()" :key="val">
                            <div class="px-6 py-3 flex flex-col sm:flex-row sm:items-center gap-3"
                                 x-init="accountMap[val] = accountMap[val] || 'new'">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate" x-text="val"></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400" x-text="rowCount(val) + ' rows'"></p>
                                </div>
                                <div class="sm:w-64 shrink-0">
                                    <select :name="'account_map[' + val + ']'" x-model="accountMap[val]"
                                            class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="new">+ Create new account</option>
                                        @foreach ($userAccounts as $ua)
                                            <option value="{{ $ua->id }}">{{ $ua->name }}</option>
                                        @endforeach
                                        <option value="skip">— Skip</option>
                                    </select>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Single destination (no account column) --}}
                    <div x-show="!columns.account" class="px-6 py-4 flex flex-col sm:flex-row sm:items-center gap-3">
                        <div class="sm:w-72 shrink-0">
                            <select name="default_account" x-model="defaultAccount"
                                    class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">— Choose an account —</option>
                                @foreach ($userAccounts as $ua)
                                    <option value="{{ $ua->id }}">{{ $ua->name }}</option>
                                @endforeach
                                <option value="new">+ Create a new account</option>
                            </select>
                        </div>
                        <input x-show="defaultAccount === 'new'" type="text" name="default_account_name"
                               placeholder="New account name" value="Imported"
                               class="sm:w-64 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>

                {{-- Live preview --}}
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Preview</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">First 20 rows as they'll import (date shown raw; parsed with the format above)</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm divide-y divide-gray-100 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Account</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Amount</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                                <template x-for="(row, i) in previewRows()" :key="i">
                                    <tr>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300 truncate max-w-[8rem]" x-text="row.account"></td>
                                        <td class="px-4 py-2 font-mono text-gray-500 dark:text-gray-400" x-text="row.date"></td>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300 truncate max-w-[14rem]" x-text="row.description"></td>
                                        <td class="px-4 py-2 text-right font-mono" x-text="row.amount"></td>
                                        <td class="px-4 py-2">
                                            <span class="text-xs px-1.5 py-0.5 rounded"
                                                  :class="row.type === 'deposit'
                                                      ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                                                      : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'"
                                                  x-text="row.type"></span>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="previewRows().length === 0">
                                    <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                                        Map a date and an amount column to see a preview.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button type="submit">Import transactions</x-primary-button>
                    <form method="POST" action="{{ route('import.csv.cancel') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition">
                            Cancel
                        </button>
                    </form>
                </div>
            </form>

        </div>
    </div>

    @push('scripts')
    <script>
        function csvImport({ headers, rows, presets, detected, fields }) {
            // Render caches (plain closure vars, not reactive) keyed on the inputs
            // they depend on, so account aggregation and the live preview don't
            // re-scan every row on every Alpine reactivity tick.
            let accountKey = null, accountCounts = {}, accountSorted = [];
            let previewKey = null, previewCache = [];

            return {
                headers,
                rows,
                presets,
                fields,
                columns: {},
                dateFormat: 'm/d/Y',
                selectedPreset: null,
                accountMap: {},
                defaultAccount: '',

                init() {
                    this.columns = this.blankColumns();
                    if (detected && this.presets[detected]) {
                        this.applyPreset(detected);
                    }
                },

                blankColumns() {
                    return Object.fromEntries(this.fields.map(f => [f.key, '']));
                },

                applyPreset(key) {
                    const preset = this.presets[key];
                    if (!preset) return;
                    const cols = this.blankColumns();
                    for (const [field, header] of Object.entries(preset.columns)) {
                        if (header && this.headers.includes(header)) cols[field] = header;
                    }
                    this.columns = cols;
                    this.dateFormat = preset.date_format;
                    this.selectedPreset = key;
                },

                clearMapping() {
                    this.columns = this.blankColumns();
                    this.selectedPreset = null;
                },

                // Distinct account values + per-value counts in a single pass,
                // memoised on the chosen account column.
                accountValues() {
                    if (accountKey !== this.columns.account) {
                        accountKey = this.columns.account;
                        accountCounts = {};
                        if (this.columns.account) {
                            for (const r of this.rows) {
                                const v = (r[this.columns.account] || '').trim();
                                if (v) accountCounts[v] = (accountCounts[v] || 0) + 1;
                            }
                        }
                        accountSorted = Object.keys(accountCounts).sort();
                    }
                    return accountSorted;
                },

                rowCount(val) {
                    return accountCounts[val] ?? 0;
                },

                parseAmount(v) {
                    const c = (v || '').replace(/[^0-9.]/g, '');
                    return c === '' ? 0 : parseFloat(c);
                },

                parseSigned(v) {
                    v = (v || '').trim();
                    const neg = v.startsWith('-') || (v.startsWith('(') && v.endsWith(')'));
                    const mag = this.parseAmount(v);
                    return neg ? -mag : mag;
                },

                cell(row, field) {
                    const h = this.columns[field];
                    return h ? (row[h] || '').trim() : '';
                },

                previewRows() {
                    // Memoised on the column mapping; the template reads this twice
                    // per render (x-for + empty-state x-show) and on every tick.
                    const key = JSON.stringify(this.columns);
                    if (previewKey === key) return previewCache;
                    previewKey = key;

                    const out = [];
                    for (const row of this.rows) {
                        let amount, type;
                        if (this.columns.amount) {
                            const s = this.parseSigned(this.cell(row, 'amount'));
                            if (s === 0) continue;
                            type = s >= 0 ? 'deposit' : 'withdrawal';
                            amount = Math.abs(s);
                        } else {
                            const inf = this.parseAmount(this.cell(row, 'inflow'));
                            const outf = this.parseAmount(this.cell(row, 'outflow'));
                            if (inf === 0 && outf === 0) continue;
                            type = inf > 0 ? 'deposit' : 'withdrawal';
                            amount = inf > 0 ? inf : outf;
                        }
                        const desc = [this.cell(row, 'payee'), this.cell(row, 'category'), this.cell(row, 'memo')].filter(Boolean).join(' · ');
                        out.push({
                            account: this.cell(row, 'account'),
                            date: this.cell(row, 'date'),
                            description: desc,
                            amount: '$' + amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                            type,
                        });
                        if (out.length >= 20) break;
                    }
                    previewCache = out;
                    return out;
                },
            };
        }
    </script>
    @endpush
</x-app-layout>

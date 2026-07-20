import './bootstrap';
import './ticker-search';
import { DEMO_MASK, fmtFull, fmtSigned } from './format-utils';

// Whole-dollar display for interactive "type any number and see the split" widgets —
// deliberately not demo-masked, since the figure being shown is whatever the visitor
// just typed (not real account data), so masking it would defeat the widget's purpose.
function fmtWhole(v) {
    // A cleared or unparseable number input yields '' / NaN — show $0 rather than '$NaN'.
    const n = Number(v);
    return '$' + (Number.isFinite(n) ? Math.round(n) : 0).toLocaleString();
}

// Livewire bundles and starts its own Alpine instance (@livewireScripts in
// layouts/app.blade.php loads it on every page, not just Livewire ones) — importing
// a second copy here would race it and produce two competing Alpine instances, which
// silently breaks Livewire's wire:* directive binding. Register custom components on
// 'alpine:init' instead, which Alpine dispatches before it walks the DOM.

document.addEventListener('alpine:init', () => {
    window.Alpine.data('budgetCalculator', (income, mandatoryPct, discretionaryPct, savingsPct) => ({
        income,
        mandatoryPct,
        discretionaryPct,
        savingsPct,
        fmt: fmtWhole,
        get needs() { return this.income * this.mandatoryPct / 100; },
        get wants() { return this.income * this.discretionaryPct / 100; },
        get savings() { return this.income * this.savingsPct / 100; },
    }));

    window.Alpine.data('holdingsSort', (rows) => {
        const demo = window.__demoMode ?? false;
        return {
            rows,
            sortCol: 'sort_value',
            sortDir: 'desc',
            openSymbol: null,
            sort(col) {
                if (this.sortCol === col) {
                    this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortCol = col;
                    this.sortDir = (col === 'symbol' || col === 'asset_type') ? 'asc' : 'desc';
                }
            },
            arrow(col) {
                return this.sortCol === col ? (this.sortDir === 'asc' ? ' ↑' : ' ↓') : '';
            },
            fmtMoney(v) {
                if (demo) return DEMO_MASK;
                if (v === null || v === undefined) return '—';
                return fmtFull(v);
            },
            fmtPrice(v) {
                if (demo) return DEMO_MASK;
                if (v === null || v === undefined) return '—';
                return '$' + v.toLocaleString('en-US', { minimumFractionDigits: 4, maximumFractionDigits: 4 });
            },
            fmtQty(v) {
                if (demo) return DEMO_MASK;
                return parseFloat(v.toFixed(8)).toString();
            },
            fmtPct(v) {
                return v.toFixed(1) + '%';
            },
            plFmt(v) {
                if (demo) return DEMO_MASK;
                if (v === null || v === undefined) return '—';
                return fmtSigned(v);
            },
            plClass(v) {
                if (v === null || v === undefined) return 'text-gray-400 dark:text-gray-500';
                return v >= 0 ? 'text-green-600' : 'text-red-600';
            },
            get sorted() {
                return [...this.rows].sort((a, b) => {
                    let av = a[this.sortCol], bv = b[this.sortCol];
                    if (av === null || av === undefined) return 1;
                    if (bv === null || bv === undefined) return -1;
                    if (typeof av === 'string') {
                        return this.sortDir === 'asc' ? av.localeCompare(bv) : bv.localeCompare(av);
                    }
                    return this.sortDir === 'asc' ? av - bv : bv - av;
                });
            },
        };
    });
});

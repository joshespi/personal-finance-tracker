import './bootstrap';

import Alpine from 'alpinejs';
window.Alpine = Alpine;

Alpine.data('holdingsSort', (rows) => ({
    rows,
    sortCol: 'sort_value',
    sortDir: 'desc',
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
        if (v === null || v === undefined) return '—';
        return '$' + v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    fmtPrice(v) {
        if (v === null || v === undefined) return '—';
        return '$' + v.toLocaleString('en-US', { minimumFractionDigits: 4, maximumFractionDigits: 4 });
    },
    fmtQty(v) {
        return parseFloat(v.toFixed(8)).toString();
    },
    fmtPct(v) {
        return v.toFixed(1) + '%';
    },
    plFmt(v) {
        if (v === null || v === undefined) return '—';
        const abs = Math.abs(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return (v >= 0 ? '+$' : '-$') + abs;
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
}));

Alpine.start();

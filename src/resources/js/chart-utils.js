export const DEMO_MASK = '••••';

export function cutoffDate(range) {
    const now = new Date();
    const y = now.getFullYear(), m = now.getMonth(), d = now.getDate();
    switch (range) {
        case '5D':  return new Date(y, m, d - 5);
        case '1W':  return new Date(y, m, d - 7);
        case '1M':  return new Date(y, m - 1, d);
        case '3M':  return new Date(y, m - 3, d);
        case '6M':  return new Date(y, m - 6, d);
        case '1Y':  return new Date(y - 1, m, d);
        case 'YTD': return new Date(y, 0, 1);
        case '5Y':  return new Date(y - 5, m, d);
        case '10Y': return new Date(y - 10, m, d);
        default:    return null;
    }
}

export function filterByRange(data, range) {
    const cut = cutoffDate(range);
    return cut ? data.filter(r => new Date(r.date) >= cut) : data;
}

const WEEK_MS = 7 * 24 * 60 * 60 * 1000;
const weekKey  = d => Math.floor(new Date(d).getTime() / WEEK_MS);
const monthKey = d => { const t = new Date(d); return t.getFullYear() * 12 + t.getMonth(); };

// Daily snapshots get noisy over long spans. Collapse to period-end points
// (weekly/monthly) so the long-range trend reads cleanly. Latest point is
// always preserved, so today's value never drifts.
export function resampleByRange(data) {
    if (data.length < 2) return data;
    const spanDays = (new Date(data[data.length - 1].date) - new Date(data[0].date)) / 86400000;
    if (spanDays <= 92) return data;
    const keyFn = spanDays <= 730 ? weekKey : monthKey;
    const buckets = new Map();
    for (const r of data) buckets.set(keyFn(r.date), r);
    return Array.from(buckets.values());
}

export function activateBtn(containerSel, activeRange) {
    document.querySelectorAll(containerSel + ' button[data-range]').forEach(b => {
        const active = b.dataset.range === activeRange;
        b.classList.toggle('bg-indigo-600', active);
        b.classList.toggle('text-white', active);
        b.classList.toggle('dark:bg-indigo-500', active);
        b.classList.toggle('bg-gray-100', !active);
        b.classList.toggle('dark:bg-gray-700', !active);
        b.classList.toggle('text-gray-600', !active);
        b.classList.toggle('dark:text-gray-300', !active);
    });
}

export function fmtK(v) {
    const abs = Math.abs(v);
    if (abs >= 1e6) return '$' + (v / 1e6).toFixed(2) + 'M';
    if (abs >= 1e3) return '$' + (v / 1e3).toFixed(1) + 'K';
    return '$' + v.toFixed(0);
}

export function fmtFull(v) {
    const str = Math.abs(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return (v < 0 ? '-$' : '$') + str;
}

export function makeTimeScales(gridColor, labelColor, yFmt) {
    return {
        x: {
            type: 'time',
            time: { unit: 'day', tooltipFormat: 'MMM d, yyyy' },
            grid: { color: gridColor },
            ticks: { color: labelColor },
        },
        y: {
            grid: { color: gridColor },
            ticks: { color: labelColor, callback: yFmt },
        },
    };
}

export function makeLegendOpts(labelColor) {
    return { display: true, position: 'bottom', labels: { color: labelColor } };
}

export function pointFromRow(r, key) {
    return { x: new Date(r.date).getTime(), y: r[key] };
}

export function buildNorm(data, key, range) {
    const f = filterByRange(data, range);
    if (!f.length) return [];
    const base = f[0][key];
    if (!base) return [];
    return f.map(r => ({ x: new Date(r.date).getTime(), y: parseFloat(((r[key] / base - 1) * 100).toFixed(2)) }));
}

export const benchTickerColors = { SPY: '#10b981', BTC: '#f59e0b' };

export const slicePalette = [
    '#6366f1','#10b981','#f59e0b','#ef4444','#3b82f6',
    '#8b5cf6','#f97316','#14b8a6','#ec4899','#84cc16',
];

import { Chart } from 'chart.js';

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

// Shared value/cost line chart (portfolio "Market Value" vs "Cost Basis").
// `data` may be an array or a () => array thunk (the dashboard switches source by
// a manual-assets toggle). `transform` post-processes the range-filtered rows
// (the dashboard resamples long spans); `onUpdate(filtered, range)` is a hook for
// side effects like the dashboard summary tiles. Returns { chart, refresh } so
// callers can re-render the current range without re-reading the active range.
export function makeValueCostChart({
    el, gridColor, labelColor, valueLabel, data, btnsSel,
    range = '1Y', transform = rows => rows, onUpdate, autoTimeUnit = false,
}) {
    if (!el) return null;

    const chart = new Chart(el, {
        type: 'line',
        data: {
            datasets: [
                { label: valueLabel,   data: [], borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.15)', fill: true,  tension: 0.3, borderWidth: 2, pointRadius: 0 },
                { label: 'Cost Basis', data: [], borderColor: '#94a3b8', borderDash: [5, 5],                        fill: false, tension: 0.3, borderWidth: 2, pointRadius: 0 },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: makeTimeScales(gridColor, labelColor, fmtK),
            plugins: {
                legend: makeLegendOpts(labelColor),
                tooltip: { callbacks: { label: ctx => `${ctx.dataset.label}: ${fmtFull(ctx.parsed.y)}` } },
            },
        },
    });

    // Let Chart.js auto-pick the time unit so resampled long-range data doesn't
    // try to render a daily tick axis.
    if (autoTimeUnit) chart.options.scales.x.time.unit = false;

    let current = range;
    function update(r) {
        current = r;
        const filtered = filterByRange(typeof data === 'function' ? data() : data, r);
        const points   = transform(filtered);
        chart.data.datasets[0].data = points.map(row => pointFromRow(row, 'value'));
        chart.data.datasets[1].data = points.map(row => pointFromRow(row, 'cost'));
        chart.update();
        activateBtn(btnsSel, r);
        if (onUpdate) onUpdate(filtered, r);
    }

    document.querySelectorAll(btnsSel + ' button[data-range]').forEach(b =>
        b.addEventListener('click', () => update(b.dataset.range))
    );
    update(current);

    return { chart, refresh: () => update(current) };
}

// Shared benchmark normalized-comparison chart: a "self" series (the portfolio,
// normalized off its `value`) plus one normalized series per benchmark ticker
// (off `price`). `selfData` may be an array or a () => array thunk.
export function makeBenchmarkChart({
    el, gridColor, labelColor, selfLabel, selfData, benchRaw, btnsSel, range = '1Y',
}) {
    if (!el || Object.keys(benchRaw).length === 0) return null;

    const chart = new Chart(el, {
        type: 'line',
        data: { datasets: [] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: makeTimeScales(gridColor, labelColor, v => v.toFixed(1) + '%'),
            plugins: {
                legend: makeLegendOpts(labelColor),
                tooltip: { callbacks: { label: ctx => `${ctx.dataset.label}: ${ctx.parsed.y.toFixed(2)}%` } },
            },
        },
    });

    function update(r) {
        const self = typeof selfData === 'function' ? selfData() : selfData;
        const datasets = [{ label: selfLabel, data: buildNorm(self, 'value', r), borderColor: '#6366f1', fill: false, tension: 0.3, borderWidth: 2, pointRadius: 0 }];
        Object.keys(benchRaw).forEach(t => datasets.push({
            label: t, data: buildNorm(benchRaw[t] || [], 'price', r), borderColor: benchTickerColors[t] || '#9ca3af', fill: false, tension: 0.3, borderWidth: 2, pointRadius: 0,
        }));
        chart.data.datasets = datasets;
        chart.update();
        activateBtn(btnsSel, r);
    }

    document.querySelectorAll(btnsSel + ' button[data-range]').forEach(b =>
        b.addEventListener('click', () => update(b.dataset.range))
    );
    update(range);

    return chart;
}

export const benchTickerColors = { SPY: '#10b981', BTC: '#f59e0b' };

export const slicePalette = [
    '#6366f1','#10b981','#f59e0b','#ef4444','#3b82f6',
    '#8b5cf6','#f97316','#14b8a6','#ec4899','#84cc16',
];

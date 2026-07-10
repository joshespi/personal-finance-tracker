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

// Same as fmtFull but always signed (e.g. gain/loss deltas), so a $0 or
// positive change reads "+$..." instead of the bare "$...".
export function fmtSigned(v) {
    const str = Math.abs(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return (v >= 0 ? '+$' : '-$') + str;
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

// Day-over-day $ and % change, keyed by date string. First date in the series
// has no prior day to diff against, so it's omitted (renders as "no data").
export function dailyChangeMap(rows) {
    const sorted = [...rows].sort((a, b) => new Date(a.date) - new Date(b.date));
    const map = new Map();
    for (let i = 1; i < sorted.length; i++) {
        const prev = sorted[i - 1].value;
        const cur  = sorted[i].value;
        if (!prev) continue;
        map.set(sorted[i].date, { dollar: cur - prev, pct: (cur - prev) / prev * 100 });
    }
    return map;
}

const MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

function dateStrFromUTC(date) {
    return date.toISOString().slice(0, 10);
}

// One GitHub-style week-grid per calendar month, each its own Sunday-aligned
// island padded with null so every column is a full week — rather than one
// continuous strip spanning month boundaries. That lets months read as
// distinct blocks and lets a prev/next pager step through history one month
// at a time regardless of total data size. `endYear`/`endMonth` (0-indexed)
// is the last month shown; `count` months are returned in chronological
// order ending there. Dates are handled in UTC throughout so day-boundary
// math doesn't drift with the browser's local timezone.
export function buildCalendarMonths(endYear, endMonth, count = 12) {
    const months = [];
    for (let i = count - 1; i >= 0; i--) {
        const d = new Date(Date.UTC(endYear, endMonth - i, 1));
        months.push({ year: d.getUTCFullYear(), month: d.getUTCMonth() });
    }

    return months.map(({ year, month }) => {
        const daysInMonth   = new Date(Date.UTC(year, month + 1, 0)).getUTCDate();
        const leadingBlank  = new Date(Date.UTC(year, month, 1)).getUTCDay();

        const cells = new Array(leadingBlank).fill(null);
        for (let day = 1; day <= daysInMonth; day++) {
            cells.push(dateStrFromUTC(new Date(Date.UTC(year, month, day))));
        }
        while (cells.length % 7 !== 0) cells.push(null);

        const weeks = [];
        for (let i = 0; i < cells.length; i += 7) weeks.push(cells.slice(i, i + 7));

        return { year, month, label: MONTH_NAMES[month], weeks };
    });
}

const CALENDAR_GREEN_SHADES = ['#bbf7d0', '#86efac', '#4ade80', '#16a34a'];
const CALENDAR_RED_SHADES   = ['#fecaca', '#fca5a5', '#f87171', '#dc2626'];

// Diverging color scale for the calendar heatmap: red (loss) <-> gray (flat)
// <-> green (gain), bucketed by magnitude so it reads consistently regardless
// of portfolio size (color is driven by %, not $).
export function calendarColor(pct, isDark) {
    if (pct === undefined) return isDark ? '#27272a' : '#f3f4f6'; // no data
    if (pct === 0) return isDark ? '#4b5563' : '#e5e7eb'; // flat

    const a     = Math.abs(pct);
    const level = a >= 2 ? 3 : a >= 1 ? 2 : a >= 0.25 ? 1 : 0;

    return pct > 0 ? CALENDAR_GREEN_SHADES[level] : CALENDAR_RED_SHADES[level];
}

export const benchTickerColors = { SPY: '#10b981', BTC: '#f59e0b' };

export const slicePalette = [
    '#6366f1','#10b981','#f59e0b','#ef4444','#3b82f6',
    '#8b5cf6','#f97316','#14b8a6','#ec4899','#84cc16',
];

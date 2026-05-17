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

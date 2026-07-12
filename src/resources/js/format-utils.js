// Pure display formatters with no Chart.js dependency. app.js (the global
// bundle) imports from here — keep this module side-effect-free so pages
// without charts never pull the Chart.js graph into their bundle.

export const DEMO_MASK = '••••';

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

// Pure display formatters with no Chart.js dependency. app.js (the global
// bundle) imports from here — keep this module side-effect-free so pages
// without charts never pull the Chart.js graph into their bundle.

export const DEMO_MASK = '••••';

export function fmtK(v, kDecimals = 1) {
    const abs = Math.abs(v);
    if (abs >= 1e6) return '$' + (v / 1e6).toFixed(2) + 'M';
    if (abs >= 1e3) return '$' + (v / 1e3).toFixed(kDecimals) + 'K';
    return '$' + v.toFixed(0);
}

export function fmtFull(v) {
    const str = Math.abs(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return (v < 0 ? '-$' : '$') + str;
}

// Round to the 2dp the signed formatters display before taking the sign, so a
// float-noise -0.001 reads "+0.00" (Math.round yields -0, and -0 >= 0) rather
// than "-0.00" in red.
const round2 = v => Math.round(v * 100) / 100;

// Same as fmtFull but always signed (e.g. gain/loss deltas), so a $0 or
// positive change reads "+$..." instead of the bare "$...".
export function fmtSigned(v) {
    const r = round2(v);
    const str = Math.abs(r).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return (r >= 0 ? '+$' : '-$') + str;
}

// Signed percent to 2dp, e.g. "+5.12%" / "-0.40%".
export function fmtSignedPct(v) {
    const r = round2(v);
    return (r >= 0 ? '+' : '-') + Math.abs(r).toFixed(2) + '%';
}

// Green/red text class for a gain/loss amount (sign judged at display precision,
// matching fmtSigned/fmtSignedPct).
export function gainLossClass(v) {
    return round2(v) >= 0 ? 'text-green-600' : 'text-red-600';
}

// "Mar 5, 2026" for a Date, read in UTC (chart rows are bare Y-m-d dates, which
// parse as UTC midnight).
export function fmtDate(d) {
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' });
}

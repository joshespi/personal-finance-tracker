import { describe, it, expect } from 'vitest';
import { resampleByRange, filterByRange } from './chart-utils';

function dailySeries(startISO, days) {
    const start = new Date(startISO).getTime();
    return Array.from({ length: days }, (_, i) => {
        const date = new Date(start + i * 86400000).toISOString().slice(0, 10);
        return { date, value: 1000 + i, cost: 500 };
    });
}

describe('resampleByRange', () => {
    it('returns the input untouched for short spans (<=92d)', () => {
        const s = dailySeries('2026-01-01', 90);
        expect(resampleByRange(s)).toBe(s);
    });

    it('buckets to weekly for spans between 3mo and 2yr', () => {
        const s = dailySeries('2025-01-01', 180);
        const out = resampleByRange(s);
        expect(out.length).toBeLessThan(s.length);
        expect(out.length).toBeGreaterThanOrEqual(25);
        expect(out.length).toBeLessThanOrEqual(27);
    });

    it('buckets to monthly for spans over 2yr', () => {
        const s = dailySeries('2020-01-01', 365 * 3);
        const out = resampleByRange(s);
        expect(out.length).toBeGreaterThanOrEqual(35);
        expect(out.length).toBeLessThanOrEqual(38);
    });

    it('always preserves the latest point so today never drifts', () => {
        const s = dailySeries('2024-01-01', 400);
        const out = resampleByRange(s);
        expect(out[out.length - 1]).toEqual(s[s.length - 1]);
    });

    it('keeps points in chronological order', () => {
        const s = dailySeries('2024-01-01', 400);
        const out = resampleByRange(s);
        const times = out.map(r => new Date(r.date).getTime());
        expect(times).toEqual([...times].sort((a, b) => a - b));
    });

    it('takes the period-end value within each bucket', () => {
        const s = dailySeries('2025-01-01', 180);
        const out = resampleByRange(s);
        // each retained row must be the max-date row of its week
        for (const r of out) {
            const wk = Math.floor(new Date(r.date).getTime() / (7 * 86400000));
            const lastOfWeek = s.filter(x => Math.floor(new Date(x.date).getTime() / (7 * 86400000)) === wk).at(-1);
            expect(r).toEqual(lastOfWeek);
        }
    });

    it('handles fewer than two points', () => {
        expect(resampleByRange([])).toEqual([]);
        const one = [{ date: '2026-01-01', value: 1, cost: 0 }];
        expect(resampleByRange(one)).toBe(one);
    });
});

describe('filterByRange', () => {
    it('returns all rows for the "All" range', () => {
        const s = dailySeries('2020-01-01', 100);
        expect(filterByRange(s, 'All')).toHaveLength(100);
    });
});

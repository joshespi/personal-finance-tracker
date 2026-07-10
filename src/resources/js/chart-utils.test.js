import { describe, it, expect } from 'vitest';
import { resampleByRange, filterByRange, dailyChangeMap, buildCalendarMonths, calendarColor } from './chart-utils';

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

describe('dailyChangeMap', () => {
    it('omits the first date (no prior day to diff against)', () => {
        const s = [
            { date: '2026-01-01', value: 1000 },
            { date: '2026-01-02', value: 1010 },
        ];
        const map = dailyChangeMap(s);
        expect(map.has('2026-01-01')).toBe(false);
        expect(map.has('2026-01-02')).toBe(true);
    });

    it('computes $ and % change relative to the previous entry', () => {
        const s = [
            { date: '2026-01-01', value: 1000 },
            { date: '2026-01-02', value: 1050 },
            { date: '2026-01-03', value: 1029 },
        ];
        const map = dailyChangeMap(s);
        expect(map.get('2026-01-02')).toEqual({ dollar: 50, pct: 5 });
        expect(map.get('2026-01-03').dollar).toBeCloseTo(-21);
        expect(map.get('2026-01-03').pct).toBeCloseTo(-2);
    });

    it('skips a day whose prior value is zero (avoids divide-by-zero)', () => {
        const s = [
            { date: '2026-01-01', value: 0 },
            { date: '2026-01-02', value: 500 },
        ];
        expect(dailyChangeMap(s).has('2026-01-02')).toBe(false);
    });

    it('sorts out-of-order input before diffing', () => {
        const s = [
            { date: '2026-01-02', value: 1010 },
            { date: '2026-01-01', value: 1000 },
        ];
        expect(dailyChangeMap(s).get('2026-01-02')).toEqual({ dollar: 10, pct: 1 });
    });
});

describe('buildCalendarMonths', () => {
    it('returns `count` months in chronological order ending at endYear/endMonth', () => {
        const months = buildCalendarMonths(2026, 2, 4); // Mar 2026, 0-indexed
        expect(months.map(m => `${m.year}-${m.month}`)).toEqual(['2025-11', '2026-0', '2026-1', '2026-2']);
    });

    it('handles year rollover when stepping back from an early month', () => {
        const months = buildCalendarMonths(2026, 1, 3); // Feb 2026
        expect(months).toEqual([
            expect.objectContaining({ year: 2025, month: 11 }),
            expect.objectContaining({ year: 2026, month: 0 }),
            expect.objectContaining({ year: 2026, month: 1 }),
        ]);
    });

    it('produces rectangular weeks of 7 days starting on Sunday', () => {
        const [month] = buildCalendarMonths(2025, 5, 1); // June 2025
        expect(month.weeks.every(w => w.length === 7)).toBe(true);
    });

    it('pads the leading and trailing days of the month with null', () => {
        // April 2026 starts on a Wednesday and has 30 days, so the grid needs
        // 3 leading blanks and 2 trailing blanks to stay rectangular.
        const [month] = buildCalendarMonths(2026, 3, 1); // April 2026
        const flat = month.weeks.flat();
        expect(flat.filter(d => d)).toHaveLength(30);
        expect(flat.filter(d => d === null)).toHaveLength(5);
    });

    it('only contains dates within that month', () => {
        const [month] = buildCalendarMonths(2026, 1, 1); // Feb 2026
        const dates = month.weeks.flat().filter(d => d);
        expect(dates.every(d => d.startsWith('2026-02'))).toBe(true);
    });

    it('labels each month with its abbreviated name', () => {
        const months = buildCalendarMonths(2026, 2, 3);
        expect(months.map(m => m.label)).toEqual(['Jan', 'Feb', 'Mar']);
    });
});

describe('calendarColor', () => {
    it('returns a distinct color for no-data vs flat', () => {
        expect(calendarColor(undefined, false)).not.toBe(calendarColor(0, false));
    });

    it('returns green shades for gains and red shades for losses', () => {
        expect(calendarColor(1.5, false)).not.toBe(calendarColor(-1.5, false));
    });

    it('scales intensity with magnitude', () => {
        expect(calendarColor(0.1, false)).not.toBe(calendarColor(3, false));
    });
});

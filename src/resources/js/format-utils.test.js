import { describe, it, expect } from 'vitest';
import { fmtDate, fmtSigned, fmtSignedPct, gainLossClass } from './format-utils';

describe('signed formatters', () => {
    it('sign positive, zero and negative values', () => {
        expect(fmtSigned(1234.5)).toBe('+$1,234.50');
        expect(fmtSigned(0)).toBe('+$0.00');
        expect(fmtSigned(-12)).toBe('-$12.00');
        expect(fmtSignedPct(5.123)).toBe('+5.12%');
        expect(fmtSignedPct(-0.4)).toBe('-0.40%');
    });

    it('treat float noise that rounds to zero as zero, not a red "-0.00"', () => {
        expect(fmtSigned(-0.001)).toBe('+$0.00');
        expect(fmtSignedPct(-0.004)).toBe('+0.00%');
        expect(gainLossClass(-0.001)).toBe('text-green-600');
    });

    it('colors real losses red', () => {
        expect(gainLossClass(-0.01)).toBe('text-red-600');
        expect(gainLossClass(5)).toBe('text-green-600');
    });
});

describe('fmtDate', () => {
    it('formats in UTC so a bare Y-m-d never shifts a day', () => {
        expect(fmtDate(new Date('2026-03-05'))).toBe('Mar 5, 2026');
    });
});

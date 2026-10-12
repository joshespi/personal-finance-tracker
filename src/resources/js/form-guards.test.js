import { describe, expect, it } from 'vitest';
import { extractConfirmMessage } from './form-guards';

describe('extractConfirmMessage', () => {
    it('pulls the message out of an inline confirm handler', () => {
        expect(extractConfirmMessage("return confirm('Delete this entry?')")).toBe('Delete this entry?');
    });

    it('handles concatenated and escaped messages', () => {
        expect(extractConfirmMessage("return confirm('Record ' + 'Rent' + ' now?')")).toBe('Record Rent now?');
        expect(extractConfirmMessage(String.raw`return confirm('Set every envelope\'s amount?')`)).toBe("Set every envelope's amount?");
    });

    it('ignores handlers that are not confirms', () => {
        expect(extractConfirmMessage(null)).toBeNull();
        expect(extractConfirmMessage('doSomething()')).toBeNull();
    });

    it('returns null for handlers that throw', () => {
        expect(extractConfirmMessage('confirm(nope.nothing)')).toBeNull();
    });
});

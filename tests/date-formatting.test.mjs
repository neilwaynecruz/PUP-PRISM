import assert from 'node:assert/strict';
import test from 'node:test';
import {
    formatDisplayDate,
    formatDisplayDateTime,
    addCalendarDays,
    manilaCalendarDate,
} from '../resources/js/lib/dateFormatting.ts';

test('calendar dates remain the same day regardless of local timezone', () => {
    assert.equal(formatDisplayDate('2025-12-09'), 'Dec 9, 2025');
    assert.equal(formatDisplayDate('2028-12-09'), 'Dec 9, 2028');
});

test('UTC timestamps are shown in Manila time', () => {
    assert.equal(
        formatDisplayDate('2025-12-08T16:00:00.000000Z'),
        'Dec 9, 2025',
    );
    assert.equal(
        formatDisplayDate('2028-12-08T16:00:00.000000Z'),
        'Dec 9, 2028',
    );
    assert.equal(
        formatDisplayDateTime('2025-12-08T16:00:00.000000Z'),
        'Dec 9, 2025, 12:00 AM',
    );
});

test('missing or invalid values use a readable fallback', () => {
    assert.equal(formatDisplayDate(null, 'Not recorded'), 'Not recorded');
    assert.equal(formatDisplayDateTime('invalid'), '—');
    assert.equal(formatDisplayDate('2025-02-30'), '—');
});

test('Manila calendar dates and offsets handle midnight and year boundaries', () => {
    assert.equal(
        manilaCalendarDate(new Date('2025-12-31T16:00:00Z')),
        '2026-01-01',
    );
    assert.equal(
        manilaCalendarDate(new Date('2025-12-31T15:59:59Z')),
        '2025-12-31',
    );
    assert.equal(addCalendarDays('2028-02-28', 1), '2028-02-29');
    assert.equal(addCalendarDays('2026-01-01', -7), '2025-12-25');
});

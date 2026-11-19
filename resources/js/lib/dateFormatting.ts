const dateOptions: Intl.DateTimeFormatOptions = {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
};

export function formatDisplayDate(
    value: string | null | undefined,
    fallback = '—',
): string {
    if (!value) {
        return fallback;
    }

    const isDateOnly = /^\d{4}-\d{2}-\d{2}$/.test(value);
    const date = new Date(isDateOnly ? `${value}T00:00:00Z` : value);

    if (
        Number.isNaN(date.getTime()) ||
        (isDateOnly && date.toISOString().slice(0, 10) !== value)
    ) {
        return fallback;
    }

    return new Intl.DateTimeFormat('en-US', {
        ...dateOptions,
        timeZone: isDateOnly ? 'UTC' : 'Asia/Manila',
    }).format(date);
}

export function manilaCalendarDate(date: Date = new Date()): string {
    const parts = new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        timeZone: 'Asia/Manila',
    }).formatToParts(date);
    const values = Object.fromEntries(
        parts.map((part) => [part.type, part.value]),
    );

    return `${values.year}-${values.month}-${values.day}`;
}

export function addCalendarDays(date: string, days: number): string {
    const result = new Date(`${date}T00:00:00Z`);
    result.setUTCDate(result.getUTCDate() + days);

    return result.toISOString().slice(0, 10);
}

export function formatDisplayDateTime(
    value: string | null | undefined,
    fallback = '—',
): string {
    if (!value) {
        return fallback;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return fallback;
    }

    return new Intl.DateTimeFormat('en-US', {
        ...dateOptions,
        hour: 'numeric',
        minute: '2-digit',
        timeZone: 'Asia/Manila',
    }).format(date);
}

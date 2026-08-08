export function formatInteger(value: number | null) {
    return value === null ? 'Not available' : value.toLocaleString('en-US');
}

export function formatDecimal(value: number | null, digits = 1) {
    return value === null
        ? 'Not available'
        : value.toLocaleString('en-US', {
              maximumFractionDigits: digits,
          });
}

export function formatPercent(value: number | null, digits = 1) {
    return value === null
        ? 'Not available'
        : `${formatDecimal(value, digits)}%`;
}

export function formatDuration(seconds: number | null) {
    if (seconds === null) {
        return 'Not available';
    }

    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remainingSeconds = seconds % 60;

    return [hours, minutes, remainingSeconds]
        .filter((_, index) => index > 0 || hours > 0)
        .map((part) => String(part).padStart(2, '0'))
        .join(':');
}

export function formatAnalysisTimestamp(
    value: string | null,
    timezone: string,
) {
    if (!value) {
        return 'Not available';
    }

    return new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: timezone,
    }).format(new Date(value));
}

import type { UiLocale } from '@/types';

const localeTags: Record<UiLocale, string> = {
    ro: 'ro-RO',
    en: 'en-US',
};

export function resolveUiLocale(locale?: string): UiLocale {
    const candidate =
        locale ??
        (typeof document === 'undefined'
            ? 'ro'
            : document.documentElement.lang);

    if (candidate === 'ro' || candidate.startsWith('ro-')) {
        return 'ro';
    }

    return 'en';
}

export function formatDate(
    value: string | null,
    timezone: string,
    locale?: string,
): string {
    const uiLocale = resolveUiLocale(locale);

    if (value === null) {
        return uiLocale === 'ro' ? 'Indisponibil' : 'Not available';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return uiLocale === 'ro' ? 'Indisponibil' : 'Not available';
    }

    return new Intl.DateTimeFormat(localeTags[uiLocale], {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: timezone,
    }).format(date);
}

export function formatNumber(value: number, locale?: string): string {
    return new Intl.NumberFormat(localeTags[resolveUiLocale(locale)], {
        maximumFractionDigits: 6,
    }).format(value);
}

import type { UiLocale } from '@/types';
import { en } from './catalogs/en';
import type { TranslationKey } from './catalogs/en';
import { ro } from './catalogs/ro';

const catalogs: Record<UiLocale, Partial<Record<TranslationKey, string>>> = {
    ro,
    en,
};

export function isUiLocale(locale: string): locale is UiLocale {
    return locale === 'ro' || locale === 'en';
}

export function translate(locale: string, key: TranslationKey): string {
    const catalog = isUiLocale(locale) ? catalogs[locale] : undefined;

    return catalog?.[key] ?? en[key];
}

export type { TranslationKey };

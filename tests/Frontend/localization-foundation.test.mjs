import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (path) =>
    readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

const selector = read('resources/js/components/locale-selector.tsx');
const localeDocument = read('resources/js/components/locale-document.tsx');
const i18n = read('resources/js/i18n/index.ts');
const englishCatalog = read('resources/js/i18n/catalogs/en.ts');
const romanianCatalog = read('resources/js/i18n/catalogs/ro.ts');
const formatters = read('resources/js/lib/formatters.ts');
const landing = read('resources/js/pages/welcome.tsx');
const authLayout = read('resources/js/layouts/auth/auth-simple-layout.tsx');
const preferences = read('resources/js/pages/settings/preferences.tsx');

test('typed catalogs use English as the per-key fallback', () => {
    assert.match(englishCatalog, /export type TranslationKey/);
    assert.match(romanianCatalog, /Partial<Record<TranslationKey, string>>/);
    assert.match(i18n, /catalog\?\.\[key\] \?\? en\[key\]/);
});

test('locale selector is shared by public, auth, and settings surfaces', () => {
    assert.match(selector, /router\.put\(/);
    assert.match(selector, /ui_locale: uiLocale/);
    assert.match(selector, /supportedLocales\.map/);
    assert.match(landing, /<LocaleSelector compact/);
    assert.match(authLayout, /<LocaleSelector/);
    assert.match(preferences, /<LocaleSelector/);
});

test('document language follows Inertia locale changes', () => {
    assert.match(localeDocument, /document\.documentElement\.lang = locale/);
    assert.match(localeDocument, /router\.on\('navigate'/);
    assert.doesNotMatch(localeDocument, /usePage/);
});

test('number and date formatters default to Romanian and fall back to English', () => {
    assert.match(formatters, /ro: 'ro-RO'/);
    assert.match(formatters, /en: 'en-US'/);
    assert.match(formatters, /typeof document === 'undefined'/);
    assert.match(formatters, /return 'en'/);
    assert.match(preferences, /formatDate\(.+locale\)/s);
    assert.match(preferences, /formatNumber\(.+locale/s);
});

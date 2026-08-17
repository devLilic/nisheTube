import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const root = new URL('../../resources/js/', import.meta.url);
const glossary = await readFile(
    new URL('components/analytics-glossary.tsx', root),
    'utf8',
);
const analyticalPages = await Promise.all(
    [
        ['pages/dashboard.tsx', 'dashboard'],
        ['pages/research/show.tsx', 'research'],
        ['pages/analyzer/show.tsx', 'analyzer'],
        ['pages/analyzer/compare.tsx', 'analyzer_compare'],
        ['pages/explore/index.tsx', 'explore'],
        ['pages/discovery/show.tsx', 'discovery'],
        ['pages/history/index.tsx', 'history'],
        ['pages/history/compare.tsx', 'history_compare'],
        ['pages/watchlist/index.tsx', 'watchlist'],
    ].map(async ([path, page]) => ({
        path,
        page,
        source: await readFile(new URL(path, root), 'utf8'),
    })),
);
const nonAnalyticalPages = await Promise.all(
    [
        'pages/analyzer/index.tsx',
        'pages/research/create.tsx',
        'pages/settings/profile.tsx',
        'pages/settings/security.tsx',
    ].map(async (path) => ({
        path,
        source: await readFile(new URL(path, root), 'utf8'),
    })),
);

test('Google-style right help panel is dismissible, scrollable, Romanian, and formula-aware', () => {
    assert.match(glossary, /<Sheet>/);
    assert.match(glossary, /side="right"/);
    assert.match(glossary, /overlayClassName="bg-black\/10"/);
    assert.match(glossary, /rounded-2xl/);
    assert.match(glossary, /overflow-y-auto/);
    assert.match(glossary, /Ajutor date/);
    assert.match(glossary, /Ce înseamnă/);
    assert.match(glossary, /Pentru ce este util/);
    assert.match(glossary, /Cum se calculează/);
    assert.match(glossary, /<strong className="text-xl[^>]*font-bold"/);
    assert.match(glossary, /Lifetime Average Views\/Day/);
    assert.match(glossary, /Nu reprezintă volumul căutărilor YouTube/);
});

test('every analytical page selects only its contextual glossary', () => {
    for (const { path, page, source } of analyticalPages) {
        assert.match(
            source,
            new RegExp(`<AnalyticsGlossary page="${page}"`),
            path,
        );
    }
});

test('non-analytical intake and settings pages expose no glossary trigger', () => {
    for (const { path, source } of nonAnalyticalPages) {
        assert.doesNotMatch(source, /AnalyticsGlossary/, path);
    }
});

test('each contextual glossary has meaning, utility, calculation, and provenance fields', () => {
    assert.match(glossary, /meaning: string/);
    assert.match(glossary, /usefulFor: string/);
    assert.match(glossary, /calculation: string/);
    assert.match(glossary, /provenance: Provenance/);

    for (const { page } of analyticalPages) {
        assert.match(glossary, new RegExp(`\\n    ${page}: \\{`), page);
    }
});

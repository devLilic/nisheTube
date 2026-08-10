import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const page = await readFile(
    new URL('../../resources/js/pages/watchlist/index.tsx', import.meta.url),
    'utf8',
);
const analyzer = await readFile(
    new URL(
        '../../resources/js/features/analyzer/analyzer-curation.tsx',
        import.meta.url,
    ),
    'utf8',
);
const explore = await readFile(
    new URL('../../resources/js/pages/explore/index.tsx', import.meta.url),
    'utf8',
);

test('watchlist page exposes explicit refresh, pause, retry, quota, partial and destructive states', () => {
    for (const copy of [
        'Refresh now',
        'Pause',
        'Resume',
        'Retry',
        'YouTube quota exhausted',
        'Partial observation',
        'Remove watched item',
    ]) {
        assert.match(page, new RegExp(copy));
    }

    assert.match(page, /Favorites remain bookmarks/);
    assert.match(page, /loading this page never calls YouTube/i);
});

test('analyzer and explore expose explicit watch handoffs without silently favoriting', () => {
    assert.match(analyzer, /action="\/watchlist"/);
    assert.match(analyzer, /remains independent from Favorites/);
    assert.match(explore, /action="\/watchlist"/);
    assert.doesNotMatch(explore, /Watchlist ships in WATCH-01/);
});

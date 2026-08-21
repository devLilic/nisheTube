import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const page = readFileSync(
    new URL(
        '../../resources/js/pages/settings/preferences.tsx',
        import.meta.url,
    ),
    'utf8',
);
const layout = readFileSync(
    new URL('../../resources/js/layouts/settings/layout.tsx', import.meta.url),
    'utf8',
);

test('preferences provide keyboard-operable timezone, market, and result-depth controls', () => {
    assert.match(page, /Research preferences/);
    assert.match(page, /id="timezone"/);
    assert.match(page, /id="default_market_key"/);
    assert.match(page, /id="default_result_depth"/);
    assert.match(page, /aria-invalid/);
    assert.match(page, /Save preferences/);
});

test('preferences make UTC, empty-market, formatting, and save states explicit', () => {
    assert.match(page, /Stored timestamps stay in UTC/);
    assert.match(page, /Ask me each time/);
    assert.match(page, /Formatting preview/);
    assert.match(page, /Enter a valid timezone to preview timestamps/);
    assert.match(page, /No enabled markets available/);
    assert.match(page, /Saving preferences/);
});

test('settings navigation exposes the consolidated preferences page', () => {
    assert.match(layout, /title: 'Preferences'/);
    assert.match(layout, /href: '\/settings\/preferences'/);
});

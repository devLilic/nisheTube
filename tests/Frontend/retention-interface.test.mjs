import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');
const page = read('../../resources/js/pages/settings/retention.tsx');
const workspace = read(
    '../../resources/js/features/retention/retention-workspace.tsx',
);
const loading = read(
    '../../resources/js/features/retention/retention-loading.tsx',
);
const settingsLayout = read('../../resources/js/layouts/settings/layout.tsx');

test('settings navigation exposes the data-retention surface', () => {
    assert.match(settingsLayout, /Data retention/);
    assert.match(settingsLayout, /\/settings\/retention/);
});

test('retention preview identifies cutoff, exact counts, preserved favorites, and empty state', () => {
    assert.match(page, /<Deferred/);
    assert.match(page, /usePoll\(\s*2000/);
    assert.match(page, /retention\?\.has_active/);
    assert.match(loading, /Loading retention data/);
    assert.match(loading, /Retention data could not be loaded/);
    assert.match(workspace, /Six-month retention preview/);
    assert.match(workspace, /Eligible runs/);
    assert.match(workspace, /Video snapshots/);
    assert.match(workspace, /Channel snapshots/);
    assert.match(workspace, /Expired exports/);
    assert.match(workspace, /favorited run/);
    assert.match(workspace, /shared source run/);
    assert.match(workspace, /Shared source preserved/);
    assert.match(workspace, /Nothing is due for cleanup/);
});

test('cleanup and selective deletion require named confirmations and expose audit outcomes', () => {
    assert.match(workspace, /retention-confirmation/);
    assert.match(workspace, /Review cleanup/);
    assert.match(workspace, /Record dry run/);
    assert.match(workspace, /Delete selected/);
    assert.match(workspace, /favorite-impact-confirmation/);
    assert.match(workspace, /Cleanup audit history/);
    assert.match(workspace, /partial outcomes/);
    assert.match(workspace, /Cleanup target outcomes/);
});

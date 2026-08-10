import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const [research, discovery, explore, workspace] = await Promise.all([
    readFile(
        new URL(
            '../../resources/js/features/research/analysis/analysis-tables.tsx',
            import.meta.url,
        ),
        'utf8',
    ),
    readFile(
        new URL(
            '../../resources/js/features/discovery/candidate-list.tsx',
            import.meta.url,
        ),
        'utf8',
    ),
    readFile(
        new URL('../../resources/js/pages/explore/index.tsx', import.meta.url),
        'utf8',
    ),
    readFile(
        new URL(
            '../../resources/js/features/integration/workspace-handoff.tsx',
            import.meta.url,
        ),
        'utf8',
    ),
]);

test('canonical result surfaces expose Analyzer, Favorite, Watchlist, and Workspace handoffs', () => {
    assert.match(research, /return_to=/);
    assert.match(research, /FavoriteToggle/);
    assert.match(research, /action="\/watchlist"/);
    assert.match(research, /WorkspaceHandoff/);
    assert.match(research, /flex flex-wrap justify-end gap-2/);
    assert.match(explore, /FavoriteToggle/);
    assert.match(explore, /action="\/watchlist"/);
    assert.match(explore, /WorkspaceHandoff/);
    assert.match(workspace, /target_reference/);
});

test('Discover labels Analyzer data as evidence and guards opportunity validation', () => {
    assert.match(discovery, /Discovery evidence/);
    assert.match(discovery, /not an opportunity/);
    assert.match(discovery, /Validation Search/);
    assert.match(discovery, /validationBlocked/);
    assert.match(discovery, /analyzerRunIds/);
    assert.match(discovery, /origin=discover/);
});

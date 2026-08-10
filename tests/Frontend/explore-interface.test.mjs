import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const page = await readFile(
    new URL('../../resources/js/pages/explore/index.tsx', import.meta.url),
    'utf8',
);
const readModel = await readFile(
    new URL(
        '../../app/Domain/Explore/ReadModels/BuildExploreIndex.php',
        import.meta.url,
    ),
    'utf8',
);

test('Explore exposes contextual filters, bounded pagination, and honest missing data', () => {
    assert.match(page, /Stored-evidence filters/);
    assert.match(page, /Source workflow/);
    assert.match(page, /Official category/);
    assert.match(page, /Breakout class/);
    assert.match(page, /Channel size/);
    assert.match(page, /Minimum score/);
    assert.match(page, /Observed from/);
    assert.match(page, /Unavailable/);
    assert.match(page, /PaginationControls/);
    assert.match(readModel, /private const PER_PAGE = 24/);
});

test('Explore distinguishes free browsing from explicit quota-aware actions', () => {
    assert.match(page, /Browsing and filtering use no YouTube API quota/);
    assert.match(page, /Analyze \(explicit\)/);
    assert.match(page, /Validate \(uses quota\)/);
    assert.match(page, /explicit quota-aware action/);
    assert.match(page, />\s*Watch\s*</);
    assert.match(page, /WorkspaceHandoff/);
    assert.match(page, /targetReference=\{item\.id\}/);
    assert.doesNotMatch(
        readModel,
        /VideoResearchProvider|ChannelUploadsProvider|YouTubeDataApiProvider/,
    );
});

test('Explore includes loading, empty, partial, and safe unavailable-module states', () => {
    assert.match(page, /aria-busy="true"/);
    assert.match(page, /No stored evidence matches/);
    assert.match(page, /Explore filters could not be applied/);
    assert.match(page, /have missing stored metrics/);
    assert.match(page, /watchlist_available/);
    assert.match(page, /workspace_available/);
    assert.match(page, /action="\/watchlist"/);
});

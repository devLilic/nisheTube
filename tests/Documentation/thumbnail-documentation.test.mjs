import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const [architecture, model, ui, api, acceptance, unified, decisions] =
    await Promise.all([
        readFile(
            new URL('../../docs/02_ARCHITECTURE.md', import.meta.url),
            'utf8',
        ),
        readFile(
            new URL('../../docs/03_DATA_MODEL.md', import.meta.url),
            'utf8',
        ),
        readFile(new URL('../../docs/05_UI_UX.md', import.meta.url), 'utf8'),
        readFile(
            new URL('../../docs/06_YOUTUBE_API.md', import.meta.url),
            'utf8',
        ),
        readFile(
            new URL('../../docs/09_ACCEPTANCE_AND_TESTING.md', import.meta.url),
            'utf8',
        ),
        readFile(
            new URL('../../docs/12_UNIFIED_ANALYZER_MODEL.md', import.meta.url),
            'utf8',
        ),
        readFile(
            new URL('../../docs/10_DECISIONS.md', import.meta.url),
            'utf8',
        ),
    ]);

test('thumbnail retrieval, storage, cache, and retention boundaries are documented', () => {
    assert.match(architecture, /ThumbnailImageFetcher/);
    assert.match(architecture, /Bytes exist only in the worker process/);
    assert.match(model, /thumbnail_analysis_profiles/);
    assert.match(model, /thumbnail_analysis_items/);
    assert.match(model, /thumbnail_performance_aggregates/);
    assert.match(model, /stores no image bytes/);
    assert.match(api, /consumes no API quota/);
    assert.match(api, /redirects disabled/);
    assert.match(acceptance, /owner-isolated cache reuse/);
    assert.match(decisions, /D-034/);
});

test('thumbnail UI and interpretation boundaries are documented', () => {
    assert.match(
        ui,
        /viewing or polling the page never starts the analysis job/,
    );
    assert.match(
        ui,
        /complete, partial, insufficient, or failed\/retry states/,
    );
    assert.match(
        unified,
        /inferred observed association rather than causation/,
    );
    assert.match(
        unified,
        /never affects Analyzer metrics or opportunity scoring/,
    );
    assert.match(unified, /Missing\/inaccessible\/invalid images/);
});

import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const [component, page, types] = await Promise.all([
    readFile(
        new URL(
            '../../resources/js/features/analyzer/thumbnail-analysis.tsx',
            import.meta.url,
        ),
        'utf8',
    ),
    readFile(
        new URL('../../resources/js/pages/analyzer/show.tsx', import.meta.url),
        'utf8',
    ),
    readFile(
        new URL('../../resources/js/types/analyzer.ts', import.meta.url),
        'utf8',
    ),
]);

test('Analyzer exposes only an explicit thumbnail-analysis action with complete states', () => {
    assert.match(page, /<ThumbnailAnalysis/);
    assert.match(page, /thumbnail_analysis\?\.is_active/);
    assert.match(component, /Thumbnail analysis is off/);
    assert.match(component, /Thumbnail analysis queued/);
    assert.match(component, /Analyzing thumbnails/);
    assert.match(component, /Insufficient comparable thumbnails/);
    assert.match(component, /Partial thumbnail evidence/);
    assert.match(component, /Thumbnail analysis failed/);
    assert.match(component, /Retry thumbnail analysis/);
    assert.match(component, /section never starts the analysis job/);
});

test('thumbnail evidence discloses inference, versions, cache, exact values, and no causation', () => {
    assert.match(component, /Inferred provenance/);
    assert.match(component, /Feature version/);
    assert.match(component, /Association version/);
    assert.match(component, /Confidence/);
    assert.match(component, /Exact visual features/);
    assert.match(component, /Evidence IDs/);
    assert.match(component, /Observed association, not causation/);
    assert.match(component, /do not alter.*opportunity\s+scoring/s);
    assert.match(
        component,
        /item\.status === 'available'\s+&&\s+item\.thumbnail_url/,
    );
    assert.match(component, /does not\s+guess whether the remote\s+image/);
    assert.match(types, /ThumbnailAnalysis/);
    assert.doesNotMatch(types, /image_bytes|raw_image/);
});

import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const panel = readFileSync(
    new URL(
        '../../resources/js/features/research/provenance-panel.tsx',
        import.meta.url,
    ),
    'utf8',
);
const page = readFileSync(
    new URL('../../resources/js/pages/research/show.tsx', import.meta.url),
    'utf8',
);

test('research results render structural API and calculated provenance', () => {
    assert.match(page, /<ProvenancePanel/);
    assert.match(panel, /Observation provenance/);
    assert.match(panel, /YouTube Data|group\.label/);
    assert.match(panel, /Calculated Metrics|group\.description/);
    assert.match(panel, /Observation window/);
    assert.match(panel, /Observation freshness/);
    assert.match(panel, /Cached observations/);
    assert.match(panel, /original observed/);
    assert.match(panel, /Collection source/);
});

test('provenance panel exposes loading, partial, empty, and error guidance', () => {
    assert.match(panel, /provenance\.state === 'loading'/);
    assert.match(panel, /provenance\.state === 'partial'/);
    assert.match(panel, /provenance\.state === 'empty'/);
    assert.match(panel, /provenance\.state === 'error'/);
    assert.match(panel, /Partial source coverage/);
    assert.match(panel, /Source context unavailable/);
    assert.match(panel, /aria-busy="true"/);
});

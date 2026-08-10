import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const [component, page] = await Promise.all([
    readFile(
        new URL(
            '../../resources/js/features/analyzer/analyzer-transcript.tsx',
            import.meta.url,
        ),
        'utf8',
    ),
    readFile(
        new URL('../../resources/js/pages/analyzer/show.tsx', import.meta.url),
        'utf8',
    ),
]);

test('Analyzer exposes an optional user-provided transcript workflow', () => {
    assert.match(page, /<AnalyzerTranscriptSection/);
    assert.match(component, /Optional user-provided evidence/);
    assert.match(component, /No transcript provided/);
    assert.match(component, /analysis remains complete without/);
    assert.match(component, /rights_confirmed/);
    assert.match(component, /Plain text, SRT, and VTT are also accepted/);
});

test('transcript viewer is searchable, timestamp-linked, and confirmably deletable', () => {
    assert.match(component, /Search transcript/);
    assert.match(component, /No matching transcript text/);
    assert.match(component, /youtube\.com\/watch\?v=/);
    assert.match(component, /Delete current transcript revision\?/);
    assert.match(
        component,
        /Video and\s+channel analysis will remain unchanged/,
    );
    assert.match(component, /Eligible for cleanup/);
});

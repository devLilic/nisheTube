import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const [component, page] = await Promise.all([
    readFile(
        new URL(
            '../../resources/js/features/analyzer/transcript-structure.tsx',
            import.meta.url,
        ),
        'utf8',
    ),
    readFile(
        new URL('../../resources/js/pages/analyzer/show.tsx', import.meta.url),
        'utf8',
    ),
]);

test('Analyzer renders inferred transcript structure separately from original text', () => {
    assert.match(page, /<TranscriptStructure/);
    assert.match(component, /Original transcript text remains/);
    assert.match(component, /Detected analysis/);
    assert.match(component, /Inferred provenance/);
    assert.match(component, /algorithm_version/);
    assert.match(component, /Confidence/);
});

test('structure surface covers all outputs, states, and exact evidence disclosure', () => {
    for (const label of [
        'Summary',
        'Topics',
        'Entities',
        'Hook',
        'Sections',
        'Calls to action',
        'Questions',
        'Script structure',
    ]) {
        assert.match(component, new RegExp(label));
    }

    assert.match(component, /Transcript structure not analyzed/);
    assert.match(component, /Analyzing structure…/);
    assert.match(component, /Insufficient transcript evidence/);
    assert.match(component, /Partial inferred output/);
    assert.match(component, /Structure analysis failed/);
    assert.match(component, /View original evidence/);
    assert.match(component, /Character offsets/);
    assert.match(component, /Open timestamped evidence/);
    assert.match(component, /no YouTube quota/i);
});

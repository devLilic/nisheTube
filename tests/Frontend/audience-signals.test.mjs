import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const [component, page, tabs, types] = await Promise.all([
    readFile(
        new URL(
            '../../resources/js/features/analyzer/audience-signals.tsx',
            import.meta.url,
        ),
        'utf8',
    ),
    readFile(
        new URL('../../resources/js/pages/analyzer/show.tsx', import.meta.url),
        'utf8',
    ),
    readFile(
        new URL(
            '../../resources/js/features/analyzer/analyzer-profile-tabs.tsx',
            import.meta.url,
        ),
        'utf8',
    ),
    readFile(
        new URL('../../resources/js/types/analyzer.ts', import.meta.url),
        'utf8',
    ),
]);

test('Audience Signals renders inferred provenance, exact evidence, confidence, and version', () => {
    assert.match(page, /<AnalyzerProfileTabs/);
    assert.match(tabs, /<AudienceSignalsSection/);
    assert.match(component, /not authoritative sentiment/);
    assert.match(component, /Analyze audience signals/);
    assert.match(component, /makes no YouTube request/);
    assert.match(component, /Inferred analysis/);
    assert.match(component, /source\s+comments/);
    assert.match(component, /Evidence for/);
    assert.match(component, /algorithm_version/);
    assert.match(component, /inferred confidence/);
});

test('Audience Signals covers all kinds and sparse multilingual unsafe failure states', () => {
    for (const label of [
        'Repeated questions',
        'Topics',
        'Entities',
        'Suggestions',
        'Complaints',
        'Confusion points',
    ]) {
        assert.match(component, new RegExp(label));
    }

    assert.match(component, /Not enough repeated evidence/);
    assert.match(component, /Unsafe output withheld/);
    assert.match(component, /Audience Signals failed/);
    assert.match(component, /Audience Signal limitation/);
    assert.match(types, /'partial' \| 'insufficient' \| 'unsafe' \| 'failed'/);
});

test('Audience Signals supports reversible exact single-word exclusions', () => {
    assert.match(component, /Hide word/);
    assert.match(component, /Hidden single words/);
    assert.match(
        component,
        /Longer\s+phrases containing the word remain visible/,
    );
    assert.match(component, /Restore \{exclusion\.word\}/);
    assert.match(component, /audience-signal-exclusions/);
    assert.match(component, /All matching signals are hidden/);
    assert.match(types, /can_exclude: boolean/);
    assert.match(types, /hidden_signal_count: number/);
    assert.match(types, /excluded_words:/);
});

import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const [page, component, types] = await Promise.all([
    readFile(
        new URL(
            '../../resources/js/pages/analyzer/compare.tsx',
            import.meta.url,
        ),
        'utf8',
    ),
    readFile(
        new URL(
            '../../resources/js/features/analyzer/cross-channel-comparison.tsx',
            import.meta.url,
        ),
        'utf8',
    ),
    readFile(
        new URL(
            '../../resources/js/types/analyzer-comparison.ts',
            import.meta.url,
        ),
        'utf8',
    ),
]);

test('cross-channel comparison exposes an ordered channel-first two-to-four selection', () => {
    assert.match(page, /Select two to four channels/i);
    assert.match(page, /ordered alphabetically/i);
    assert.match(page, /Comparison set/);
    assert.match(page, /Available channels/);
    assert.match(page, /Filter available channels/);
    assert.match(page, /selectedIds\.length ===[\s\S]*4/);
    assert.match(page, /third: selectedIds\[2\]/);
    assert.match(page, /fourth: selectedIds\[3\]/);
    assert.match(page, /option\.attempts\.map/);
    assert.match(page, /Not enough channel analyses/);
    assert.match(page, /Choose channels to compare/);
    assert.match(page, /Loading comparison/);
    assert.match(component, /Comparison compatibility warning/);
    assert.match(component, /Not detected/);
    assert.match(component, /Not available/);
    assert.match(types, /AnalyzerComparisonAttemptOption/);
    assert.match(
        types,
        /AnalyzerComparisonRun,[\s\S]*AnalyzerComparisonRun,[\s\S]*AnalyzerComparisonRun,[\s\S]*AnalyzerComparisonRun/,
    );
});

test('comparison uses exact accessible tables and makes no score, cause, or recommendation claim', () => {
    assert.match(component, /<caption className="sr-only">/);
    assert.match(component, /Exact observed cohort values/);
    assert.match(component, /Median views\/day/);
    assert.match(component, /Breakout rate/);
    assert.match(component, /Stored peer evidence labels/);
    assert.match(component, /Niche concentration/);
    assert.match(component, /n=\{item\.sample_count\}/);
    assert.match(types, /CrossChannelComparison/);
    assert.doesNotMatch(component, /opportunity score.*\+/i);
});

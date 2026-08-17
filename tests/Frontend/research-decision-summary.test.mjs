import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const component = readFileSync(
    new URL(
        '../../resources/js/features/research/decision-summary.tsx',
        import.meta.url,
    ),
    'utf8',
);
const page = readFileSync(
    new URL('../../resources/js/pages/research/show.tsx', import.meta.url),
    'utf8',
);

test('Research leads with one decision summary and explicit lifecycle semantics', () => {
    assert.match(page, /<ResearchDecisionSummary/);
    assert.doesNotMatch(page, /<RunStatus/);
    assert.match(component, /Decision verdict/);
    assert.match(component, /Recommended next action/);
    assert.match(component, /Field-level completeness/);
    assert.match(component, /Principal evidence/);
    assert.match(component, /Decision risks/);
    assert.match(component, /summary\.stability\.label/);
});

test('Active progress has exact persisted counts and avoids a precise unsupported ETA', () => {
    assert.match(component, /summary\.active_progress\.stage/);
    assert.match(component, /summary\.active_progress\.collected_count/);
    assert.match(component, /summary\.active_progress\s*\.warning_count/);
    assert.match(component, /summary\.active_progress\.eta_label/);
    assert.match(component, /summary\.active_progress\.eta_explanation/);
    assert.doesNotMatch(component, /minutes? remaining/i);
});

test('Terminal progress is compact collection detail instead of a competing headline', () => {
    assert.match(page, /Collection details/);
    assert.match(page, /Persisted progress/);
    assert.match(page, /decision_summary\?\.lifecycle/);
    assert.doesNotMatch(page, /<CardTitle>Run progress<\/CardTitle>/);
    assert.doesNotMatch(page, /Snapshot complete/);
});

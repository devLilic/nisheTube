import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const page = readFileSync(
    new URL('../../resources/js/pages/research/show.tsx', import.meta.url),
    'utf8',
);
const evidence = readFileSync(
    new URL(
        '../../resources/js/features/research/evidence-inspection.tsx',
        import.meta.url,
    ),
    'utf8',
);
const actions = readFileSync(
    new URL(
        '../../resources/js/features/research/research-actions.tsx',
        import.meta.url,
    ),
    'utf8',
);
const provenance = readFileSync(
    new URL(
        '../../resources/js/features/research/provenance-panel.tsx',
        import.meta.url,
    ),
    'utf8',
);
const score = readFileSync(
    new URL(
        '../../resources/js/features/research/scoring/opportunity-score-section.tsx',
        import.meta.url,
    ),
    'utf8',
);
const profitabilityFit = readFileSync(
    new URL(
        '../../resources/js/features/research/scoring/profitability-fit-section.tsx',
        import.meta.url,
    ),
    'utf8',
);

test('Research evidence uses bounded server controls and exact accessible rows', () => {
    assert.match(page, /<ResearchEvidenceInspection/);
    assert.match(evidence, /only: \['run'\]/);
    assert.match(evidence, /Evidence quick filters/);
    assert.match(evidence, /Strictly relevant|filter\.label/);
    assert.match(evidence, /filter\.label/);
    assert.match(evidence, /overflow-x-auto/);
    assert.match(evidence, /Exact row context/);
    assert.match(evidence, /Not available/);
    assert.match(evidence, /return_to=/);
});

test('Research keeps one primary shortlist action and secondary handoffs in a keyboard menu', () => {
    assert.match(page, /<ResearchActions/);
    assert.match(actions, /context="shortlist"/);
    assert.match(actions, /primary/);
    assert.match(actions, /More actions/);
    assert.match(actions, /Discover related\s+themes/);
    assert.match(actions, /Compare snapshots/);
    assert.match(actions, /Repeat snapshot/);
    assert.match(actions, /Add to\s+workspace/);
    assert.match(actions, /Export this run/);
});

test('Components and provenance progressively disclose dense evidence', () => {
    assert.match(score, /<details/);
    assert.match(score, /Expand evidence/);
    assert.match(provenance, /<Sheet>/);
    assert.match(provenance, /Inspect provenance/);
    assert.match(provenance, /Provider and frozen request/);
    assert.match(provenance, /Provider endpoints/);
    assert.match(
        provenance,
        /Observation window, cache, formula, and snapshots/,
    );
});

test('Profitability fit keeps estimates and legacy availability explicit', () => {
    assert.match(page, /<ProfitabilityFitSection/);
    assert.match(profitabilityFit, /Estimated profitability fit/);
    assert.match(profitabilityFit, /not revenue or profit/);
    assert.match(profitabilityFit, /legacy result/);
    assert.match(profitabilityFit, /never calls YouTube/);
});

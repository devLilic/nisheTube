import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (name) =>
    readFileSync(new URL(`../../docs/${name}`, import.meta.url), 'utf8');

test('research evidence version, persistence, UI, and acceptance rules agree', () => {
    const data = read('03_DATA_MODEL.md');
    const scoring = read('04_SCORING_MODEL.md');
    const ui = read('05_UI_UX.md');
    const acceptance = read('09_ACCEPTANCE_AND_TESTING.md');
    const decisions = read('10_DECISIONS.md');

    for (const document of [data, scoring, acceptance, decisions]) {
        assert.match(document, /research-evidence-v1/);
    }

    assert.match(data, /research_evidence_profiles/);
    assert.match(data, /research_result_evidence/);
    assert.match(scoring, /Shorts, long-form, and unknown-format samples/);
    assert.match(scoring, /At least three overlapping videos/);
    assert.match(ui, /complete-versus-strict robust statistics/);
    assert.match(ui, /never declares a cross-format winner/);
    assert.match(acceptance, /duplicate titles/);
    assert.match(decisions, /D-041/);
    assert.match(decisions, /niche-opportunity-v1/);
});

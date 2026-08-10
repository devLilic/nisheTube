import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const [model, scoring, ui, api, decisions] = await Promise.all([
    readFile(new URL('../../docs/03_DATA_MODEL.md', import.meta.url), 'utf8'),
    readFile(
        new URL('../../docs/04_SCORING_MODEL.md', import.meta.url),
        'utf8',
    ),
    readFile(new URL('../../docs/05_UI_UX.md', import.meta.url), 'utf8'),
    readFile(new URL('../../docs/06_YOUTUBE_API.md', import.meta.url), 'utf8'),
    readFile(new URL('../../docs/10_DECISIONS.md', import.meta.url), 'utf8'),
]);

test('Audience Signal persistence, inference, evidence, and states are documented', () => {
    assert.match(model, /audience_signal_evidence/);
    assert.match(model, /exact stored top-level comments/);
    assert.match(scoring, /audience-comment-terms-v1/);
    assert.match(scoring, /not authoritative sentiment/);
    assert.match(
        ui,
        /Sparse, mixed-language\/partial, unsafe-withheld, failed, fully-hidden, and successful states/,
    );
});

test('Audience Signals add no provider request and retain an extensible contract', () => {
    assert.match(api, /performs no provider request/);
    assert.match(api, /Audience Signal provider contract/);
    assert.match(decisions, /D-031/);
    assert.match(decisions, /relational evidence links/);
});

test('Audience Signal word exclusions are exact, reversible, and profile-safe', () => {
    assert.match(model, /audience_signal_exclusions/);
    assert.match(
        model,
        /multi-word label containing an excluded word remains visible/,
    );
    assert.match(ui, /hide an unhelpful single-word signal and restore it/);
    assert.match(decisions, /D-036/);
    assert.match(decisions, /without recalculation/);
    assert.match(
        decisions,
        /no comment collection, provider request, or signal-profile mutation/,
    );
});

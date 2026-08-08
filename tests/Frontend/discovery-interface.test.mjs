import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const candidateSource = readFileSync(
    new URL('../../resources/js/features/discovery/candidate-list.tsx', import.meta.url),
    'utf8',
);
const formSource = readFileSync(
    new URL('../../resources/js/features/discovery/discovery-form.tsx', import.meta.url),
    'utf8',
);
const showSource = readFileSync(
    new URL('../../resources/js/pages/discovery/show.tsx', import.meta.url),
    'utf8',
);

test('discovery creation exposes seeds, market, budget, and stored-sample quota guidance', () => {
    assert.match(formSource, /Seed phrase/);
    assert.match(formSource, /Evidence depth per seed/);
    assert.match(formSource, /Candidate budget/);
    assert.match(formSource, /No discovery collection calls/);
    assert.match(formSource, /No completed samples in this market/);
});

test('candidate results expose evidence, filters, lifecycle actions, and safe video links', () => {
    assert.match(candidateSource, /Minimum score/);
    assert.match(candidateSource, /Minimum confidence/);
    assert.match(candidateSource, /Save/);
    assert.match(candidateSource, /Dismiss/);
    assert.match(candidateSource, /Validate/);
    assert.match(candidateSource, /No candidates match these filters/);
    assert.match(candidateSource, /target="_blank"/);
    assert.match(candidateSource, /rel="noopener noreferrer"/);
});

test('run detail provides polling progress, retry, failure, and completed result states', () => {
    assert.match(showSource, /usePoll/);
    assert.match(showSource, /DiscoveryProgress/);
    assert.match(showSource, /Retry analysis/);
    assert.match(showSource, /Discovery analysis could not finish/);
    assert.match(showSource, /Partial sample evidence/);
    assert.match(showSource, /CandidateList/);
});

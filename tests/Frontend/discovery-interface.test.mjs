import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const candidateSource = readFileSync(
    new URL(
        '../../resources/js/features/discovery/candidate-list.tsx',
        import.meta.url,
    ),
    'utf8',
);
const formSource = readFileSync(
    new URL(
        '../../resources/js/features/discovery/discovery-form.tsx',
        import.meta.url,
    ),
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

test('candidate results use a compact paginated decision table with expanded exact evidence', () => {
    assert.match(candidateSource, /Minimum evidence score/);
    assert.match(candidateSource, /Minimum confidence/);
    assert.match(candidateSource, /Candidate niches/);
    assert.match(candidateSource, /Weak phrase signals/);
    assert.match(candidateSource, /PaginationControls/);
    assert.match(candidateSource, /SortableHead/);
    assert.match(candidateSource, /aria-expanded/);
    assert.match(candidateSource, /Expanded evidence/);
    assert.match(candidateSource, /Outlier-free median views\/day/);
    assert.match(candidateSource, /Other \/ unavailable channel size/);
    assert.match(candidateSource, /Secondary\s+actions/);
    assert.match(candidateSource, /Save/);
    assert.match(candidateSource, /Dismiss/);
    assert.match(candidateSource, /Validate/);
    assert.match(candidateSource, /Why this remains a weak signal/);
    assert.match(candidateSource, /Suggested query/);
    assert.match(candidateSource, /Channel IDs/);
    assert.match(candidateSource, /Evidence videos/);
    assert.match(candidateSource, /Sources/);
    assert.match(candidateSource, /Risks/);
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

import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const page = readFileSync(
    new URL('../../resources/js/pages/shortlist/index.tsx', import.meta.url),
    'utf8',
);

test('shortlist keeps frozen evidence, selection bounds, and unavailable states explicit', () => {
    assert.match(page, /Select two to five/);
    assert.match(page, /never calls\s+YouTube or recalculates history/);
    assert.match(page, /Your shortlist is empty/);
    assert.match(page, /Frozen comparison/);
    assert.match(page, /Legacy \/ score unavailable/);
    assert.match(page, /Unavailable for this legacy result/);
});

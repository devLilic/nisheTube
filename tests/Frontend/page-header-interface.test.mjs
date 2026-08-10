import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const header = readFileSync(
    new URL('../../resources/js/components/page-header.tsx', import.meta.url),
    'utf8',
);

test('shared page headers are compact by default across every page', () => {
    assert.match(header, /compact = true/);
    assert.match(header, /eyebrow && !compact/);
    assert.match(header, /line-clamp-2/);
    assert.match(header, /lg:line-clamp-1/);
    assert.match(header, /text-xl sm:text-2xl/);
});

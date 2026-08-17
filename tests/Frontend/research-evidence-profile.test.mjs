import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const page = readFileSync(
    new URL('../../resources/js/pages/research/show.tsx', import.meta.url),
    'utf8',
);
const profile = readFileSync(
    new URL(
        '../../resources/js/features/research/research-evidence-profile.tsx',
        import.meta.url,
    ),
    'utf8',
);
const inspection = readFileSync(
    new URL(
        '../../resources/js/features/research/evidence-inspection.tsx',
        import.meta.url,
    ),
    'utf8',
);

test('Research exposes full, strict, format, outlier, and compatible-snapshot evidence accessibly', () => {
    assert.match(page, /<ResearchEvidenceProfile/);
    assert.match(profile, /Complete returned sample/);
    assert.match(profile, /Strictly relevant sample/);
    assert.match(profile, /Format-specific evidence/);
    assert.match(profile, /Shorts and long-form are\s+reported independently/);
    assert.match(profile, /Outlier resistance/);
    assert.match(profile, /removed_top_count/);
    assert.match(profile, /Compatible-snapshot stability/);
    assert.match(profile, /<caption className="sr-only">/);
});

test('Per-result relevance discloses exact non-color signals and keeps legacy fallback', () => {
    assert.match(inspection, /item\.relevance\.class\.replaceAll/);
    assert.match(inspection, /Title coverage/);
    assert.match(inspection, /Semantic\/category\/topic\s+matches/);
    assert.match(inspection, /Negative terms/);
    assert.match(inspection, /Provider rank/);
    assert.match(inspection, /<details>/);
});

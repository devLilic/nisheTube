import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');
const validation = read('../../resources/js/features/research/search-form.tsx');
const discovery = read(
    '../../resources/js/features/discovery/discovery-form.tsx',
);
const catalog = read(
    '../../app/Domain/Research/Services/ResearchIntakeCatalog.php',
);

test('validation intake exposes every preset, exact frozen preflight, and honest demand copy', () => {
    for (const label of [
        'Fast scan',
        'Balanced',
        'Deep validation',
        'Trend check',
        'Emerging trend',
        'Evergreen check',
        'Small-channel opportunity',
        'Long-form documentary',
        'Shorts opportunity',
    ]) {
        assert.match(catalog, new RegExp(label));
    }

    assert.match(validation, /Exact preflight/);
    assert.match(validation, /Frozen on creation/);
    assert.match(validation, /not YouTube search\s+volume/);
    assert.match(validation, /Creating run/);
    assert.match(validation, /form\.processing/);
    assert.match(validation, /Advanced filters/);
});

test('market discovery captures all required lenses and has a safe single-submit state', () => {
    for (const label of [
        'Language',
        'Content format',
        'Period',
        'Target channel size',
    ]) {
        assert.match(discovery, new RegExp(label));
    }

    assert.match(discovery, /submission_token/);
    assert.match(discovery, /Creating run/);
    assert.match(discovery, /form\.processing/);
    assert.match(discovery, /Generate initial themes/);
});

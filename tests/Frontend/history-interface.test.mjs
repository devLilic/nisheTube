import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');
const indexPage = read('../../resources/js/pages/history/index.tsx');
const comparePage = read('../../resources/js/pages/history/compare.tsx');
const overview = read(
    '../../resources/js/features/history/history-overview.tsx',
);
const comparison = read(
    '../../resources/js/features/history/comparison-view.tsx',
);

test('history index provides deferred loading, timeline, pair selection, and insufficient-history guidance', () => {
    assert.match(indexPage, /<Deferred/);
    assert.match(overview, /Run timeline/);
    assert.match(overview, /Baseline snapshot/);
    assert.match(overview, /Comparison snapshot/);
    assert.match(overview, /Not enough compatible history/);
    assert.match(overview, /Score .*Confidence/s);
});

test('comparison renders warnings, deltas, accessible component values, and entity changes', () => {
    assert.match(comparePage, /<Deferred/);
    assert.match(comparison, /Component score change/);
    assert.match(comparison, /View exact component values/);
    assert.match(comparison, /Collection compatibility/);
    assert.match(comparison, /Metric deltas/);
    assert.match(comparison, /Video changes/);
    assert.match(comparison, /Channel composition/);
    assert.match(comparison, /formula versions differ/);
});

test('comparison exposes safe direct YouTube destinations for changed entities', () => {
    assert.match(comparison, /youtube\.com\/watch\?v=/);
    assert.match(comparison, /youtube\.com\/channel\//);
    assert.match(comparison, /target="_blank"/);
    assert.match(comparison, /rel="noopener noreferrer"/);
});

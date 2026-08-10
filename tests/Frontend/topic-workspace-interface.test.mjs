import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const index = readFileSync(
    new URL('../../resources/js/pages/topics/index.tsx', import.meta.url),
    'utf8',
);
const show = readFileSync(
    new URL('../../resources/js/pages/topics/show.tsx', import.meta.url),
    'utf8',
);

test('workspace list exposes creation, market context, filters, and empty state', () => {
    for (const marker of [
        'New workspace',
        'Primary market',
        'Apply filters',
        'No Topic Workspaces here',
        'metric payloads',
    ]) {
        assert.match(index, new RegExp(marker));
    }
});

test('workspace detail exposes roles, cross-market warnings, archive safety, and quota-aware confirmations', () => {
    for (const marker of [
        'counterexample',
        'cross_market_warning',
        'read-only archived state',
        'Confirm and queue Search',
        'Confirm and queue Discover',
        'consumes no YouTube quota',
    ]) {
        assert.match(show, new RegExp(marker, 'i'));
    }
});

test('workspace detail links canonical evidence and displays workflow history without copied metrics', () => {
    for (const marker of [
        'Link existing evidence',
        'typed reference',
        'Linked workflow history',
        'metric payloads are never copied',
        'Link evidence',
    ]) {
        assert.match(show, new RegExp(marker, 'i'));
    }
});

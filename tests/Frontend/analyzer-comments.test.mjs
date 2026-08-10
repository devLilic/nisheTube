import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const [component, profileTabs] = await Promise.all([
    readFile(
        new URL(
            '../../resources/js/features/analyzer/analyzer-comments.tsx',
            import.meta.url,
        ),
        'utf8',
    ),
    readFile(
        new URL(
            '../../resources/js/features/analyzer/analyzer-profile-tabs.tsx',
            import.meta.url,
        ),
        'utf8',
    ),
]);

test('Analyzer comments are explicit and distinguish every required state', () => {
    assert.match(profileTabs, /<AnalyzerCommentsSection/);
    assert.match(component, /Comment collection is off/);
    assert.match(component, /Collect public comments/);
    assert.match(component, /Comments are disabled/);
    assert.match(component, /No public comments returned/);
    assert.match(component, /Partial comment sample/);
    assert.match(component, /Comment quota unavailable/);
    assert.match(component, /Comments are unavailable/);
    assert.match(component, /Comment collection failed/);
});

test('scope, reply completeness, provenance, and retention are honest', () => {
    assert.match(component, /Author\s+identity and reply text are not stored/);
    assert.match(component, /Reply counts are YouTube-reported totals/);
    assert.match(component, /Eligible for cleanup/);
    assert.match(component, /additional\s+YouTube quota/);
});

test('stored comments expose bounded accessible pagination without provider collection', () => {
    assert.match(component, /comments\.pagination\.last_page > 1/);
    assert.match(component, /aria-label="Comment pages"/);
    assert.match(component, /Showing \{comments\.pagination\.from\}/);
    assert.match(component, /Previous/);
    assert.match(component, /Next/);
    assert.match(component, /comments_page: page/);
    assert.match(component, /only: \['run'\]/);
    assert.match(component, /preserveScroll: true/);
});

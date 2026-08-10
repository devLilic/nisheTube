import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const [model, ui, api, decisions] = await Promise.all([
    readFile(new URL('../../docs/03_DATA_MODEL.md', import.meta.url), 'utf8'),
    readFile(new URL('../../docs/05_UI_UX.md', import.meta.url), 'utf8'),
    readFile(new URL('../../docs/06_YOUTUBE_API.md', import.meta.url), 'utf8'),
    readFile(new URL('../../docs/10_DECISIONS.md', import.meta.url), 'utf8'),
]);

test('comment collection storage and interface boundaries are documented', () => {
    assert.match(model, /comment_collection_runs/);
    assert.match(model, /Do not store author identity or reply text/);
    assert.match(ui, /Loading a profile never collects comments/);
    assert.match(ui, /reported reply counts do not imply reply completeness/);
});

test('provider, pagination, quota, retention, and architectural decisions are explicit', () => {
    assert.match(api, /commentThreads\.list/);
    assert.match(api, /general quota bucket/);
    assert.match(api, /persisted page token/);
    assert.match(decisions, /D-030/);
    assert.match(decisions, /independent six-month cleanup targets/);
});

test('saved comment ideas preserve private intent and source-video context without provider work', () => {
    assert.match(model, /saved_comment_ideas/);
    assert.match(model, /canonical video link remain/);
    assert.match(ui, /keyboard-accessible heart control/);
    assert.match(ui, /partial-source notice/);
    assert.match(api, /Saving or removing a collected comment/);
    assert.match(decisions, /D-037/);
    assert.match(decisions, /outlive raw comment retention/i);
});

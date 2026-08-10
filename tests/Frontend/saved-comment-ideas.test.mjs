import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const [comments, page, sidebar, types] = await Promise.all([
    readFile(
        new URL(
            '../../resources/js/features/analyzer/analyzer-comments.tsx',
            import.meta.url,
        ),
        'utf8',
    ),
    readFile(
        new URL('../../resources/js/pages/ideas/index.tsx', import.meta.url),
        'utf8',
    ),
    readFile(
        new URL(
            '../../resources/js/components/app-sidebar.tsx',
            import.meta.url,
        ),
        'utf8',
    ),
    readFile(
        new URL('../../resources/js/types/ideas.ts', import.meta.url),
        'utf8',
    ),
]);

test('Analyzer comments expose reversible heart controls with loading and error feedback', () => {
    assert.match(comments, /Save comment to Ideas/);
    assert.match(comments, /Remove comment from Ideas/);
    assert.match(comments, /comment\.is_saved \? 'delete' : 'post'/);
    assert.match(comments, /disableWhileProcessing/);
    assert.match(comments, /Updating\.\.\./);
    assert.match(comments, /role="alert"/);
});

test('Ideas page lists exact saved messages with safe source-video links and reversible removal', () => {
    assert.match(sidebar, /title: 'Ideas'/);
    assert.match(page, /Saved comment ideas/);
    assert.match(page, /No saved ideas yet/);
    assert.match(page, /Open source video/);
    assert.match(page, /target="_blank"/);
    assert.match(page, /rel="noreferrer noopener"/);
    assert.match(page, /Remove like/);
    assert.match(page, /Original collection cleaned up/);
    assert.match(page, /Saved ideas pagination/);
    assert.match(types, /source_comment_available: boolean/);
    assert.match(types, /youtube_url: string/);
});

import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');
const page = read('../../resources/js/pages/exports/index.tsx');
const workspace = read(
    '../../resources/js/features/exports/export-workspace.tsx',
);

test('exports page provides deferred loading and active-job polling', () => {
    assert.match(page, /<Deferred/);
    assert.match(page, /usePoll\(\s*2000/);
    assert.match(page, /jobs\?\.has_active/);
});

test('export builder exposes run, format, column, summary, and partial-data states', () => {
    assert.match(workspace, /Research runs/);
    assert.match(workspace, /File format/);
    assert.match(workspace, /Columns/);
    assert.match(workspace, /Video rows/);
    assert.match(workspace, /Partial-data warnings included/);
    assert.match(workspace, /No completed runs available/);
});

test('export jobs expose progress, download, retry, expiry, failure, and confirmed deletion', () => {
    assert.match(workspace, /Generating/);
    assert.match(workspace, /Download\s*<\/a>/);
    assert.match(workspace, /Retry\s*<\/Button>/);
    assert.match(workspace, /Expired; create a new\s+export/);
    assert.match(workspace, /Generation failed safely/);
    assert.match(workspace, /Delete export file\?/);
    assert.match(
        workspace,
        /underlying\s+research\s+snapshots\s+will\s+not\s+be\s+changed/,
    );
});

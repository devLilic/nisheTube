import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');
const readme = read('../../README.md');
const setup = read('../../docs/07_LOCAL_SETUP.md');
const recovery = read('../../docs/11_BACKUP_AND_RECOVERY.md');
const youtube = read('../../docs/06_YOUTUBE_API.md');
const backlog = read('../../docs/08_BACKLOG.md');
const taskStatus = read('../../docs/TASK_STATUS.md');
const exportConfig = read('../../config/exports.php');
const exportJob = read('../../app/Jobs/Exports/GenerateResearchExport.php');
const retentionCommand = read(
    '../../app/Console/Commands/RunRetentionCleanup.php',
);

test('setup links the complete recovery procedure and current release state', () => {
    assert.match(readme, /local MVP feature backlog is implemented/);
    assert.match(readme, /docs\/11_BACKUP_AND_RECOVERY\.md/);
    assert.match(setup, /11_BACKUP_AND_RECOVERY\.md/);
    assert.match(setup, /php artisan retention:cleanup --dry-run/);
});

test('release documentation task closes the initial backlog without inventing a next task', () => {
    assert.match(backlog, /\[x\] \*\*REL-04 — Documentation and recovery/);
    assert.match(taskStatus, /\| Active task \| None \|/);
    assert.match(taskStatus, /\| REL-04 \| Completed \| REL-03 \|/);
    assert.doesNotMatch(taskStatus, /\| REL-\d+ \| In progress \|/);
});

test('backup guide covers database, private exports, secrets, and reversible restore', () => {
    assert.match(recovery, /mysqldump\.exe/);
    assert.match(recovery, /--single-transaction/);
    assert.match(recovery, /storage\\app\\private\\exports/);
    assert.match(recovery, /The `.env` copy is secret/);
    assert.match(recovery, /emergency backup/);
    assert.match(recovery, /before-restore/);
    assert.match(recovery, /Do not use `migrate:fresh`/);
});

test('export and retention instructions match implementation defaults', () => {
    assert.match(exportConfig, /EXPORT_EXPIRY_DAYS', 7/);
    assert.match(exportJob, /"exports\/\{\$export->user_id\}/);
    assert.match(recovery, /EXPORT_EXPIRY_DAYS=7/);
    assert.match(recovery, /<user-id>\\<export-public-id>/);
    assert.match(retentionCommand, /retention:cleanup/);
    assert.match(retentionCommand, /--dry-run/);
    assert.match(recovery, /php artisan retention:cleanup --dry-run/);
});

test('quota documentation distinguishes the local Pacific ledger from Google quota', () => {
    for (const document of [youtube, recovery]) {
        assert.match(document, /YOUTUBE_QUOTA_RESET_TIMEZONE/);
        assert.match(document, /America\/Los_Angeles/);
        assert.match(document, /Google Cloud Console/);
        assert.match(document, /local/);
    }
});

test('troubleshooting includes safe diagnostics for core local failure modes', () => {
    assert.match(recovery, /php artisan migrate:status/);
    assert.match(recovery, /php artisan queue:failed/);
    assert.match(recovery, /php artisan queue:retry <job-id>/);
    assert.match(recovery, /storage\\logs\\laravel\.log/);
    assert.match(recovery, /Never paste the key into logs/);
});

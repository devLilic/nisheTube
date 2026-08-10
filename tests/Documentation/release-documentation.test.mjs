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
const product = read('../../docs/01_PRODUCT_REQUIREMENTS.md');
const architecture = read('../../docs/02_ARCHITECTURE.md');
const dataModel = read('../../docs/03_DATA_MODEL.md');
const uiUx = read('../../docs/05_UI_UX.md');
const acceptance = read('../../docs/09_ACCEPTANCE_AND_TESTING.md');
const decisions = read('../../docs/10_DECISIONS.md');
const analyzerModel = read('../../docs/12_UNIFIED_ANALYZER_MODEL.md');
const exportConfig = read('../../config/exports.php');
const exportJob = read('../../app/Jobs/Exports/GenerateResearchExport.php');
const retentionCommand = read(
    '../../app/Console/Commands/RunRetentionCleanup.php',
);

test('setup links the complete recovery procedure and current release state', () => {
    assert.match(readme, /integrated local research expansion is implemented/);
    assert.match(readme, /docs\/11_BACKUP_AND_RECOVERY\.md/);
    assert.match(setup, /11_BACKUP_AND_RECOVERY\.md/);
    assert.match(setup, /php artisan retention:cleanup --dry-run/);
    assert.match(setup, /Search → Analyzer → Explore/);
    assert.match(
        setup,
        /Never rehearse rollback against the live `nishetube` database/,
    );
});

test('release documentation closes the completed Analyzer expansion without a next task', () => {
    assert.match(backlog, /\[x\] \*\*REL-04 — Documentation and recovery/);
    assert.match(backlog, /\[x\] \*\*DOC-01 — Unified analyzer/);
    assert.match(
        backlog,
        /\[x\] \*\*REL-05 — Integrated research expansion hardening/,
    );
    assert.match(taskStatus, /\|\s*Active task\s*\|\s*None\s*\|/);
    assert.match(taskStatus, /\|\s*REL-04\s*\|\s*Completed\s*\|\s*REL-03\s*\|/);
    assert.match(taskStatus, /\|\s*REL-05\s*\|\s*Completed\s*\|/);
});

test('Analyzer planning documents define one integrated source-aware model', () => {
    for (const document of [
        product,
        architecture,
        dataModel,
        uiUx,
        youtube,
        acceptance,
        decisions,
        analyzerModel,
    ]) {
        assert.match(document, /Analyzer/);
    }

    assert.match(analyzerModel, /Search, Analyzer, and Watchlist/);
    assert.match(analyzerModel, /Explore page loads never call YouTube/);
    assert.match(analyzerModel, /Topic Workspace/);
    assert.match(analyzerModel, /`api`/);
    assert.match(analyzerModel, /`calculated`/);
    assert.match(analyzerModel, /`inferred`/);
    assert.match(analyzerModel, /`estimated`/);
    assert.match(dataModel, /### `collection_runs`/);
    assert.match(decisions, /D-017 — Shared collection boundary/);
    assert.match(backlog, /\*\*COL-01 — Shared collection runs/);
    assert.match(backlog, /\*\*ANA-01 — Analyzer intake/);
    assert.match(backlog, /\*\*XPLR-01 — Stored-evidence Explore/);
    assert.match(backlog, /\*\*WATCH-01 — Video and channel Watchlist/);
    assert.match(backlog, /\*\*TOPIC-01 — Topic Workspace evidence hub/);

    const plannedTasks = [
        'COL-01',
        'COL-02',
        'ANA-01',
        'ANA-02',
        'ANA-03',
        'ANA-04',
        'ANA-05',
        'XPLR-01',
        'WATCH-01',
        'TOPIC-01',
        'INT-01',
        'SEM-01',
        'SEM-02',
        'COMM-01',
        'AUD-01',
        'TRN-01',
        'TRN-02',
        'THMB-01',
        'XCMP-01',
        'REL-05',
    ];

    for (const task of plannedTasks) {
        assert.match(backlog, new RegExp(`\\*\\*${task} —`));
        assert.match(taskStatus, new RegExp(`^\\|\\s*${task}\\s*\\|`, 'm'));
    }

    const activeTask = taskStatus.match(
        /^\|\s*Active task\s*\|\s*(None|[A-Z0-9-]+)\s*\|/m,
    );
    const activeRows = [
        ...taskStatus.matchAll(/^\|\s*([A-Z0-9-]+)\s*\|\s*In progress\s*\|/gm),
    ];

    if (activeTask?.[1] === 'None') {
        assert.equal(activeRows.length, 0);
    } else {
        assert.equal(activeRows.length, 1);
        assert.equal(activeRows[0][1], activeTask?.[1]);
    }
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

test('expansion migration recovery is isolated, reversible, and user-data safe', () => {
    assert.match(recovery, /19 migrations/);
    assert.match(recovery, /rel05-migration-rehearsal\.sqlite/);
    assert.match(recovery, /DB_CONNECTION = 'sqlite'/);
    assert.match(recovery, /migrate:rollback --step=19 --force/);
    assert.match(recovery, /Expansion re-apply rehearsal failed/);
    assert.match(recovery, /Never point this command sequence at MySQL/);
    assert.match(recovery, /loading Explore does not add a quota-ledger event/);
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

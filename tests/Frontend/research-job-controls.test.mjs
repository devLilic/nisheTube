import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');
const page = read('../../resources/js/pages/research/show.tsx');
const types = read('../../resources/js/types/research.ts');
const notifications = read(
    '../../resources/js/components/completed-run-notifications.tsx',
);

test('research job controls expose queued cancellation and a worker-unavailable state', () => {
    assert.match(page, /run\.job_control\.can_cancel/);
    assert.match(page, /Cancel queued run/);
    assert.match(page, /Cancelling queued run/);
    assert.match(page, /worker_unavailable/);
    assert.match(page, /Local queue worker may not be running/);
    assert.match(page, /disableWhileProcessing/);
    assert.match(
        types,
        /'not_applicable' \| 'waiting' \| 'worker_unavailable'/,
    );
});

test('notifications retain accessible unread state for terminal job outcomes', () => {
    assert.match(notifications, /Notifications\$\{notifications\.unread_count/);
    assert.match(notifications, /Mark all read/);
    assert.match(notifications, /aria-hidden="true"/);
});

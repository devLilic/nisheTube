import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');
const header = read('../../resources/js/components/app-sidebar-header.tsx');
const search = read('../../resources/js/components/global-research-search.tsx');
const notifications = read(
    '../../resources/js/components/completed-run-notifications.tsx',
);
const quota = read('../../resources/js/components/quota-widget.tsx');
const meter = read('../../resources/js/components/quota-meter.tsx');
const config = read('../../config/youtube.php');
const uiSpec = read('../../docs/05_UI_UX.md');
const backlog = read('../../docs/08_BACKLOG.md');
const status = read('../../docs/TASK_STATUS.md');

test('authenticated header exposes decision workflow controls', () => {
    assert.match(header, /GlobalResearchSearch/);
    assert.match(header, /ResearchContextControls/);
    assert.match(header, /Open Shortlist/);
    assert.match(header, /CompletedRunNotifications/);
    assert.match(header, /QuotaWidget/);
});

test('global search is debounced, bounded, accessible, and provider-free in its copy', () => {
    assert.match(search, /DEBOUNCE_MS = 300/);
    assert.match(search, /MIN_QUERY_LENGTH = 2/);
    assert.match(search, /maxLength=\{80\}/);
    assert.match(search, /first 20 bounded results/);
    assert.match(search, /Searching never calls\s+YouTube/);
    assert.match(search, /aria-live="polite"/);
    assert.match(search, /AbortController/);
});

test('notifications expose empty, unread, and persistent read interactions', () => {
    assert.match(notifications, /No notifications yet/);
    assert.match(notifications, /unread_count/);
    assert.match(notifications, /Mark all read/);
    assert.match(notifications, /completed-run-notifications\/read/);
    assert.match(notifications, /aria-label=/);
});

test('quota uses configuration-backed request and unit vocabulary', () => {
    assert.match(config, /'measure' => 'requests'/);
    assert.match(config, /'measure' => 'units'/);
    assert.match(quota, /NisheTube-recorded requests/);
    assert.match(quota, /Google-estimated units/);
    assert.match(quota, /Last NisheTube-recorded call/);
    assert.match(quota, /bucket\.last_cost/);
    assert.match(quota, /bucket\.last_occurred_at/);
    assert.match(meter, /summary\.measure/);
    assert.doesNotMatch(`${quota}\n${meter}`, /token/i);
});

test('IA-02 documentation is current and SRCH-05 is the only promoted task', () => {
    assert.match(
        uiSpec,
        /owner-visible stored themes, videos, channels, and runs/,
    );
    assert.match(backlog, /\[x\] \*\*IA-02/);
    assert.match(status, /\| Active task\s+\| SRCH-05/);
    assert.match(status, /\| IA-02 \| Completed/);
    assert.match(status, /\| SRCH-05 \| In progress/);
});

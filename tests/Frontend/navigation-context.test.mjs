import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');
const sidebar = read('../../resources/js/components/app-sidebar.tsx');
const nav = read('../../resources/js/components/nav-main.tsx');
const shell = read('../../resources/js/components/app-shell.tsx');
const content = read('../../resources/js/components/app-content.tsx');
const controls = read(
    '../../resources/js/components/research-context-controls.tsx',
);
const routes = read('../../routes/web.php');
const backlog = read('../../docs/08_BACKLOG.md');
const decisions = read('../../docs/10_DECISIONS.md');
const redesign = read('../../docs/13_DECISION_WORKFLOW_REDESIGN.md');

test('production navigation is grouped around the research decision workflow', () => {
    for (const group of [
        "label: 'Research'",
        "label: 'Library'",
        "label: 'Tools'",
    ]) {
        assert.match(sidebar, new RegExp(group));
    }

    for (const label of [
        'Dashboard',
        'Discover themes',
        'Validate a niche',
        'Explore',
        'Compare niches',
        'History',
        'Shortlist',
        'Topic Workspaces',
        'Watchlist',
        'Projects',
        'Ideas',
        'Analyzer',
        'Exports',
        'Settings',
    ]) {
        assert.match(sidebar, new RegExp(`title: '${label}'`));
    }

    assert.doesNotMatch(sidebar, /UI showcase|design-system/);
    assert.match(routes, /app\(\)->environment\('local'\)/);
});

test('active navigation and skip navigation are explicit and keyboard accessible', () => {
    assert.match(nav, /aria-current=/);
    assert.match(nav, /excludedPaths/);
    assert.match(nav, /setOpenMobile\(false\)/);
    assert.match(shell, /Skip to main content/);
    assert.match(shell, /focus:translate-y-0/);
    assert.match(shell, /event\.preventDefault\(\)/);
    assert.match(shell, /requestAnimationFrame\(\(\) => main\?\.focus\(\)\)/);
    assert.match(content, /id="main-content"/);
    assert.match(content, /tabIndex=\{-1\}/);
});

test('research context exposes bounded desktop selectors and complete save states', () => {
    assert.match(controls, /Market/);
    assert.match(controls, /Project/);
    assert.match(controls, /Workspace/);
    assert.match(controls, /router\.put\('\/research-context'/);
    assert.match(controls, /Saving research context/);
    assert.match(controls, /Research context saved/);
    assert.match(controls, /could not be saved/);
    assert.match(controls, /overflow-x-auto/);
    assert.match(controls, /truncate/);
    assert.match(controls, /aria-live="polite"/);
    assert.match(controls, /<SelectGroup>/);
    assert.match(controls, /<SelectLabel>\{label\}<\/SelectLabel>/);
});

test('the active slice and future interface QA follow the desktop-only decision', () => {
    assert.match(backlog, /IA-01[\s\S]*complete desktop states/);
    assert.match(
        decisions,
        /D-038 — Interface implementation and QA are desktop-only/,
    );
    assert.match(
        redesign,
        /visual verification is desktop-only at approximately 1440px/,
    );
    assert.doesNotMatch(
        redesign,
        /Required responsive verification remains desktop at 1440px and tablet at 768px/,
    );
});

import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');
const sidebar = read('../../resources/js/components/app-sidebar.tsx');
const header = read('../../resources/js/components/app-sidebar-header.tsx');
const shell = read('../../resources/js/components/app-shell.tsx');
const layout = read('../../resources/js/layouts/app/app-sidebar-layout.tsx');
const controls = read(
    '../../resources/js/components/research-context-controls.tsx',
);

test('research-journey navigation provides every group and corrected destinations', () => {
    for (const group of [
        'Discover',
        'Validate',
        'Analyze',
        'Organize',
        'Manage',
    ])
        assert.match(sidebar, new RegExp(`label: '${group}'`));
    assert.match(sidebar, /title: 'Compare niches',[\s\S]*href: '\/history'/);
    assert.match(sidebar, /title: 'Shortlist', href: '\/shortlist'/);
    assert.doesNotMatch(sidebar, /title: 'Shortlist', href: '\/favorites'/);
    assert.match(sidebar, /aria-current=/);
    assert.match(sidebar, /TooltipContent side="right"/);
    assert.match(sidebar, /w-\[\'72px\'\]|w-\[72px\]/);
    assert.match(sidebar, /w-\[\'248px\'\]|w-\[248px\]/);
});

test('command bar keeps mobile navigation complete and context responsive', () => {
    assert.match(header, /side="left"/);
    assert.match(header, /open=\{mobileOpen\}/);
    assert.match(header, /onNavigate=\{\(\) => setMobileOpen\(false\)\}/);
    assert.match(header, /<GlobalResearchSearch \/>/);
    assert.match(header, /<ResearchContextControls \/>/);
    assert.match(header, /<CompletedRunNotifications \/>/);
    assert.match(header, /<QuotaWidget \/>/);
    assert.match(header, /xl:hidden/);
    assert.match(controls, /router\.put\('\/research-context'/);
});

test('one responsive shell protects skip navigation and bounded canvas layout', () => {
    assert.match(shell, /Skip to main content/);
    assert.match(shell, /requestAnimationFrame\(\(\) => main\?\.focus\(\)\)/);
    assert.match(layout, /<AppCanvas/);
    assert.match(layout, /max-w-\[1680px\]/);
    assert.match(layout, /window\.innerWidth < 1200/);
    assert.match(layout, /id="main-content"/);
    assert.match(layout, /tabIndex=\{-1\}/);
});

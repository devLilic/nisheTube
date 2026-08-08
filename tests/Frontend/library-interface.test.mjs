import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');
const projects = read('../../resources/js/pages/library/projects/index.tsx');
const project = read('../../resources/js/pages/library/projects/show.tsx');
const favorites = read('../../resources/js/pages/library/favorites/index.tsx');
const favoriteCard = read(
    '../../resources/js/features/library/favorite-card.tsx',
);
const favoriteToggle = read(
    '../../resources/js/features/library/favorite-toggle.tsx',
);
const analysis = read(
    '../../resources/js/features/research/analysis/analysis-tables.tsx',
);
const discovery = read(
    '../../resources/js/features/discovery/candidate-list.tsx',
);

test('project library exposes grid/list, filters, sorting, empty state, tabs, and confirmations', () => {
    assert.match(projects, /Grid view/);
    assert.match(projects, /List view/);
    assert.match(projects, /Recently updated/);
    assert.match(projects, /No projects found/);
    assert.match(project, /role="tablist"/);
    assert.match(project, /Delete project\?/);
    assert.match(project, /Archive project\?/);
});

test('favorites expose type, project, tag and note controls with explicit removal confirmation', () => {
    assert.match(favorites, /All types/);
    assert.match(favorites, /All projects/);
    assert.match(favorites, /All tags/);
    assert.match(favoriteCard, /Add research notes/);
    assert.match(favoriteCard, /Remove favorite\?/);
    assert.match(
        favoriteToggle,
        /underlying\s+research data will not be deleted/,
    );
});

test('research analysis and discovery candidates provide favorite actions', () => {
    assert.match(analysis, /targetType="video"/);
    assert.match(analysis, /targetType="channel"/);
    assert.match(discovery, /targetType="niche_candidate"/);
});

import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');
const navFooter = read('../../resources/js/components/nav-footer.tsx');
const projectsIndex = read(
    '../../resources/js/pages/library/projects/index.tsx',
);
const analysisCharts = read(
    '../../resources/js/features/research/analysis/analysis-charts.tsx',
);
const analysisTables = read(
    '../../resources/js/features/research/analysis/analysis-tables.tsx',
);
const scoreTrend = read(
    '../../resources/js/features/dashboard/score-trend.tsx',
);
const comparison = read(
    '../../resources/js/features/history/comparison-view.tsx',
);
const analyzerProfile = read(
    '../../resources/js/features/analyzer/analyzer-profile.tsx',
);
const analyzerCohort = read(
    '../../resources/js/features/analyzer/analyzer-channel-cohort.tsx',
);
const analyzerGrowth = read(
    '../../resources/js/features/analyzer/analyzer-growth-history.tsx',
);
const watchlist = read('../../resources/js/pages/watchlist/index.tsx');
const topicWorkspace = read('../../resources/js/pages/topics/show.tsx');
const crossChannelComparison = read(
    '../../resources/js/features/analyzer/cross-channel-comparison.tsx',
);

test('chart visuals expose labels and exact-value text alternatives', () => {
    assert.match(analysisCharts, /<ol aria-label=\{label\}/);
    assert.match(analysisCharts, /complete text alternative/);
    assert.match(analysisTables, /<caption className="sr-only">/);
    assert.match(scoreTrend, /View exact trend values/);
    assert.match(scoreTrend, /<Table>/);
    assert.match(comparison, /View exact component values/);
    assert.match(comparison, /<Table>/);
});

test('focusable chart alternatives retain visible keyboard focus', () => {
    assert.match(
        scoreTrend,
        /<summary className="[^"]*focus-visible:ring-2[^"]*"/,
    );
    assert.match(
        comparison,
        /<summary className="[^"]*focus-visible:ring-2[^"]*"/,
    );
});

test('sidebar contrast and project cards remain readable across themes and tablet widths', () => {
    assert.match(navFooter, /text-sidebar-foreground\/75/);
    assert.doesNotMatch(navFooter, /text-neutral-600/);
    assert.match(projectsIndex, /grid gap-4 lg:grid-cols-2 xl:grid-cols-3/);
    assert.match(projectsIndex, /line-clamp-2 text-sm text-muted-foreground/);
});

test('expanded research surfaces preserve multilingual wrapping and tablet-safe exact values', () => {
    for (const source of [
        analyzerProfile,
        analyzerCohort,
        watchlist,
        topicWorkspace,
    ]) {
        assert.match(source, /break-words/);
    }

    for (const source of [analyzerProfile, watchlist, topicWorkspace]) {
        assert.match(source, /min-w-0/);
    }

    assert.match(analyzerCohort, /min-w-72/);
    assert.match(analyzerGrowth, /overflow-x-auto/);
    assert.match(crossChannelComparison, /<caption className="sr-only">/);
    assert.match(crossChannelComparison, /whitespace-normal/);
});

test('expanded interactive states remain named and keyboard visible', () => {
    assert.match(analyzerProfile, /focus-visible:ring-2/);
    assert.match(analyzerCohort, /aria-labelledby="channel-baseline-heading"/);
    assert.match(watchlist, /aria-valuenow=/);
    assert.match(topicWorkspace, /aria-label={`Remove \${item.label}`}/);
});

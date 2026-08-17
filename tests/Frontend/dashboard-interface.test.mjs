import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const overview = readFileSync(
    new URL(
        '../../resources/js/features/dashboard/dashboard-overview.tsx',
        import.meta.url,
    ),
    'utf8',
);
const page = readFileSync(
    new URL('../../resources/js/pages/dashboard.tsx', import.meta.url),
    'utf8',
);
const cockpit = readFileSync(
    new URL(
        '../../resources/js/features/dashboard/decision-cockpit.tsx',
        import.meta.url,
    ),
    'utf8',
);

test('dashboard surfaces the owner research toolkit with direct destinations', () => {
    assert.match(overview, /Research toolkit/);
    assert.match(overview, /Analyzer profiles/);
    assert.match(overview, /Monitored targets/);
    assert.match(overview, /Topic workspaces/);
    assert.match(overview, /Inferred profiles/);
    assert.match(overview, /href="\/analyzer"/);
    assert.match(overview, /href="\/watchlist"/);
    assert.match(overview, /href="\/topics"/);
    assert.match(page, /href="\/discover"/);
});

test('dashboard header is compact and metric explanations are accessible', () => {
    assert.match(page, /title="Dashboard"[\s\S]*compact/);
    assert.match(overview, /MetricHint/);
    assert.match(overview, /Owner-scoped totals/);
    assert.match(overview, /bg-primary\/\[0\.045\]/);
});

test('dashboard decision cockpit keeps stored estimates and unavailable states explicit', () => {
    assert.match(overview, /<DecisionCockpit/);
    assert.match(cockpit, /Decision cockpit/);
    assert.match(cockpit, /Estimated profitability fit/);
    assert.match(cockpit, /does not measure revenue, RPM,\s+costs, or profit/);
    assert.match(cockpit, /legacy scored result/);
    assert.match(cockpit, /does not call YouTube/);
});

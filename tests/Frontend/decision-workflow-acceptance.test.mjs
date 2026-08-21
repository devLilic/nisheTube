import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const read = (path) => readFile(new URL(path, import.meta.url), 'utf8');

const [
    discovery,
    researchPage,
    decisionSummary,
    comparison,
    workspace,
    watchlist,
    routes,
] = await Promise.all([
    read('../../resources/js/features/discovery/candidate-list.tsx'),
    read('../../resources/js/pages/research/show.tsx'),
    read('../../resources/js/features/research/decision-summary.tsx'),
    read('../../resources/js/pages/analyzer/compare.tsx'),
    read('../../resources/js/pages/topics/show.tsx'),
    read('../../resources/js/pages/watchlist/index.tsx'),
    read('../../routes/web.php'),
]);

test('the direct theme-to-verdict path keeps evidence, opportunity, profitability, and comparison claims distinct', () => {
    assert.match(discovery, /Evidence score is not Opportunity score/);
    assert.match(discovery, /Why this remains a weak signal/);
    assert.match(discovery, /Validate/);
    assert.match(discovery, /WorkspaceHandoff/);

    for (const label of [
        'Decision verdict',
        'Confidence',
        'Field-level completeness',
        'Stability',
        'Decision risks',
        'Recommended next action',
    ]) {
        assert.match(decisionSummary, new RegExp(label));
    }

    assert.match(researchPage, /<OpportunityScoreSection/);
    assert.match(researchPage, /<ProfitabilityFitSection/);
    assert.match(researchPage, /Workspace handoff/);
    assert.match(comparison, /Select two to four channels/i);
    assert.match(comparison, /without creating a score or recommendation/);
    assert.match(workspace, /Decision canvas/);
    assert.match(workspace, /Next action/);
});

test('monitoring stays an explicit post-verdict workflow with safe stored-data states', () => {
    assert.match(watchlist, /Watchlist notifications/);
    assert.match(watchlist, /Partial observation/);
    assert.match(watchlist, /loading this page never calls YouTube/i);
    assert.match(
        routes,
        /Route::post\('discover\/candidates\/\{nicheCandidate\}\/validate'/,
    );
    assert.match(routes, /Route::get\('analyzer\/compare'/);
    assert.match(routes, /Route::get\('topics'/);
    assert.match(routes, /Route::get\('watchlist'/);
});

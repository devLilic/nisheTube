import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const intake = readFileSync(
    new URL(
        '../../resources/js/features/analyzer/analyzer-intake-form.tsx',
        import.meta.url,
    ),
    'utf8',
);
const profile = readFileSync(
    new URL(
        '../../resources/js/features/analyzer/analyzer-profile.tsx',
        import.meta.url,
    ),
    'utf8',
);
const analyzerStatus = readFileSync(
    new URL(
        '../../resources/js/features/analyzer/analyzer-status.tsx',
        import.meta.url,
    ),
    'utf8',
);
const show = readFileSync(
    new URL('../../resources/js/pages/analyzer/show.tsx', import.meta.url),
    'utf8',
);
const profileTabs = readFileSync(
    new URL(
        '../../resources/js/features/analyzer/analyzer-profile-tabs.tsx',
        import.meta.url,
    ),
    'utf8',
);
const index = readFileSync(
    new URL('../../resources/js/pages/analyzer/index.tsx', import.meta.url),
    'utf8',
);
const cohort = readFileSync(
    new URL(
        '../../resources/js/features/analyzer/analyzer-channel-cohort.tsx',
        import.meta.url,
    ),
    'utf8',
);
const growth = readFileSync(
    new URL(
        '../../resources/js/features/analyzer/analyzer-growth-history.tsx',
        import.meta.url,
    ),
    'utf8',
);
const curation = readFileSync(
    new URL(
        '../../resources/js/features/analyzer/analyzer-curation.tsx',
        import.meta.url,
    ),
    'utf8',
);
const searchTable = readFileSync(
    new URL(
        '../../resources/js/features/research/analysis/analysis-tables.tsx',
        import.meta.url,
    ),
    'utf8',
);
const decisionSummary = readFileSync(
    new URL(
        '../../resources/js/features/analyzer/analyzer-decision-summary.tsx',
        import.meta.url,
    ),
    'utf8',
);
const rawData = readFileSync(
    new URL(
        '../../resources/js/features/analyzer/analyzer-raw-data.tsx',
        import.meta.url,
    ),
    'utf8',
);
const topicProfile = readFileSync(
    new URL(
        '../../resources/js/features/analyzer/topic-profile.tsx',
        import.meta.url,
    ),
    'utf8',
);

test('Analyzer intake documents canonical supported inputs and validation state', () => {
    assert.match(intake, /watch, youtu\.be, Shorts, embed/);
    assert.match(intake, /aria-invalid/);
    assert.match(intake, /Queueing analysis/);
    assert.match(intake, /Loading this form makes\s+no\s+provider request/);
});

test('Analyzer live page exposes progress, cache, partial, quota, and refresh states', () => {
    assert.match(show, /Live persisted\s+updates/);
    assert.match(show, /role="progressbar"/);
    assert.match(show, /PartialDataBanner/);
    assert.match(show, /Force Refresh/);
    assert.match(show, /Return to Explore/);
    assert.match(show, /youtubeQuota/);
    assert.match(show, /Video unavailable|run\.error\.title/);
    assert.match(
        analyzerStatus,
        /loading_recent_videos: 'Loading recent videos'/,
    );
});

test('profiles separate YouTube and calculated provenance with exact source context', () => {
    assert.match(profile, /YouTube Data/);
    assert.match(profile, /Calculated Metrics/);
    assert.match(profile, /Lifetime Average Views\/Day/);
    assert.match(profile, /not\s+current velocity/);
    assert.match(profile, /original\s+observation time/);
    assert.match(profile, /First seen/);
    assert.match(profile, /Author Channel Profile/);
});

test('Search video rows link to the canonical Analyzer handoff', () => {
    assert.match(searchTable, /\/analyzer\?video=/);
    assert.match(searchTable, /origin=search/);
    assert.match(searchTable, /origin_reference=/);
    assert.match(searchTable, /Open \$\{video\.title\} in Analyzer/);
    assert.match(searchTable, />\s*Analyze\s*</);
});

test('channel baseline and recent videos expose exact, sortable, filterable, responsive evidence', () => {
    assert.match(cohort, /Recent Channel Baseline/);
    assert.match(cohort, /Median views/);
    assert.match(cohort, /Videos \/ week/);
    assert.match(cohort, /Official category profile/);
    assert.match(cohort, /Filter by title or category/);
    assert.match(cohort, /Playlist position/);
    assert.match(cohort, /Lifetime views\/day/);
    assert.match(cohort, /overflow-x-auto|<Table>/);
    assert.match(cohort, /No valid recent uploads/);
    assert.match(cohort, /PartialDataBanner/);
    assert.match(cohort, /Last 90 days/);
    assert.match(cohort, /bounded stored cohort/);
    assert.match(cohort, /published_within_recent_window/);
    assert.match(cohort, /\/analyzer\?video=/);
    assert.match(cohort, /return_to=/);
    assert.match(cohort, /aria-label=\{`Analyze \$\{video\.title\}`\}/);
    assert.match(cohort, /video\.local_analysis/);
    assert.match(cohort, /View data/);
    assert.match(cohort, /View stored data for \$\{video\.title\}/);
    assert.match(cohort, /no provider\s+request/i);
});

test('relative performance keeps threshold, age, ranking, outlier, and empty-state context visible', () => {
    assert.match(profile, /Channel-relative performance/);
    assert.match(profile, /anchor is excluded\s+from its baseline/i);
    assert.match(profile, /Empirical percentile/);
    assert.match(profile, /Raw-view class shown with age context/);
    assert.match(profile, /Lifetime Average Views\/Day/);
    assert.match(cohort, /Strong \/ Breakout Outliers/);
    assert.match(cohort, /Breakout is strictly above/);
    assert.match(cohort, /No Strong or Breakout outliers/);
    assert.match(cohort, /Filter by relative class/);
    assert.match(cohort, /Highest channel-relative ratio/);
    assert.match(cohort, /Relative class/);
});

test('channel behavior and observed growth remain versioned, accessible, and honest about history', () => {
    assert.match(profileTabs, /AnalyzerGrowthHistory/);
    assert.match(growth, /Channel Behavior/);
    assert.match(growth, /Recent momentum/);
    assert.match(growth, /MAD\/median/);
    assert.match(growth, /Spearman correlation/);
    assert.match(growth, /not causation/);
    assert.match(growth, /Growth History/);
    assert.match(growth, /Observed Recent Views\/Day/);
    assert.match(growth, /Lifetime Average Views\/Day/);
    assert.match(
        growth,
        /does not backfill activity before the first observed snapshot/,
    );
    assert.match(growth, /Exact accessible values\s+follow in the table/);
    assert.match(growth, /six-month snapshot retention boundary/);
    assert.match(growth, /Cached attempts reusing the same snapshot/);
});

test('standalone channel intake and recent analyses expose the shared Analyzer route', () => {
    assert.match(intake, /Analyze a video or channel/);
    assert.match(intake, /canonical \/channel\/ URLs/);
    assert.match(profile, /Analyze this channel directly/);
    assert.match(show, /AnalyzerProfileTabs/);
    assert.match(show, /title="Analyzer profile"/);
    assert.match(show, /compact/);
    assert.match(index, /run\.display_label/);
    assert.match(index, /display_identity[\s\S]*channel_title/);
    assert.match(index, /YouTube ID:/);
    assert.match(index, /line-clamp-2/);
    assert.match(index, /Recent videos/);
    assert.match(index, /Recent channels/);
    assert.match(index, /recentVideoRuns/);
    assert.match(index, /recentChannelRuns/);
    assert.match(index, /lg:grid-cols-2/);
    assert.match(index, /display_identity\.thumbnail_url/);
    assert.match(index, /thumbnail unavailable/);
    assert.match(index, /PaginationControls/);
    assert.match(index, /title="Recent videos"/);
    assert.match(index, /title="Recent channels"/);
    assert.match(index, /ariaLabel=\{`\$\{title\} pagination`\}/);
    assert.match(index, /video_page: videoPage/);
    assert.match(index, /channel_page: channelPage/);
});

test('Analyzer profile groups decision evidence into accessible compact tabs', () => {
    assert.match(profileTabs, /role="tablist"/);
    assert.match(profileTabs, /role="tab"/);
    assert.match(profileTabs, /role="tabpanel"/);
    assert.match(profileTabs, /aria-selected/);
    assert.match(profileTabs, /ArrowRight/);
    assert.match(profileTabs, /Summary/);
    assert.match(profileTabs, /Content patterns/);
    assert.match(profileTabs, /Channel/);
    assert.match(profileTabs, /Raw data/);
    assert.match(profileTabs, /gap-5/);
    assert.match(profileTabs, /Channel profile is not ready/);
    assert.match(profileTabs, /AnalyzerDecisionSummary/);
    assert.match(profileTabs, /AnalyzerRawData/);
});

test('Analyzer decision views keep exact stored provenance and quality guardrails visible', () => {
    assert.match(decisionSummary, /Stored evidence only/);
    assert.match(
        decisionSummary,
        /not a prediction,[\s\S]*YouTube search-volume[\s\S]*measure/,
    );
    assert.match(rawData, /Values are[\s\S]*not recalculated on this page/);
    assert.match(rawData, /Lifetime views\/day formula/);
    assert.match(rawData, /Topic profile version/);
    assert.match(topicProfile, /stored[\s\S]*confidence and frequency guard/);
    assert.match(topicProfile, /Title fragment:/);
    assert.match(
        topicProfile,
        /Topic confidence applies to each repeated topic/,
    );
    assert.match(curation, /context="shortlist"/);
    assert.match(curation, /WorkspaceHandoff/);
});

test('important video and channel metrics use soft accents and hover or focus explanations', () => {
    assert.match(profile, /MetricHint/);
    assert.match(profile, /bg-primary\/\[0\.055\]/);
    assert.match(profile, /Explain|explanation=/);
    assert.match(profile, /Versus channel median/);
    assert.match(profile, /Subscribers/);
});

test('Analyzer curation keeps confirmations, library compatibility, Watchlist, and workspace handoff explicit', () => {
    assert.match(curation, /Private research note/);
    assert.match(curation, /Research status/);
    assert.match(curation, /FavoriteToggle/);
    assert.match(curation, /library\/tags/);
    assert.match(curation, /Mark this \{subject\.type\} as ruled out/);
    assert.match(curation, /Add to Watchlist/);
    assert.match(curation, /WorkspaceHandoff/);
    assert.match(curation, /targetType="analyzer_run"/);
    assert.match(curation, /targetReference=\{run\.public_id\}/);
});

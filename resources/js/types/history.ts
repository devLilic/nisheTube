import type { MarketKey, ResearchRunStatus } from './research';

export type HistoryScore = {
    overall_score: number;
    confidence_score: number;
    formula_version: string;
    calculated_at: string;
};

export type HistoryRun = {
    public_id: string;
    query_text: string;
    market_key: MarketKey;
    market_name: string;
    kind: 'search' | 'discovery_validation';
    status: ResearchRunStatus;
    attempt_number: number;
    parameters: Record<string, unknown>;
    requested_result_count: number;
    collected_result_count: number;
    collection_warnings: string[];
    created_at: string | null;
    completed_at: string | null;
    failed_at: string | null;
    score: HistoryScore | null;
    can_compare: boolean;
};

export type HistoryCandidate = {
    public_id: string;
    completed_at: string | null;
    requested_result_count: number;
    collected_result_count: number;
    formula_version: string | null;
    overall_score: number | null;
    confidence_score: number | null;
    score_comparable: boolean;
    warning_codes: string[];
};

export type HistoryIndexData = {
    runs: HistoryRun[];
    selected_anchor: string | null;
    candidates: HistoryCandidate[];
    repeat_source: HistoryRun | null;
    filters: {
        q: string;
        market: string | null;
        status: ResearchRunStatus | null;
        min_score: number | null;
        min_confidence: number | null;
        date_from: string | null;
        date_to: string | null;
        project: string | null;
        workspace: string | null;
        page: number;
        per_page: 10 | 25 | 50;
    };
    pagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filter_options: {
        markets: string[];
        projects: { public_id: string; name: string }[];
        workspaces: { public_id: string; name: string }[];
    };
    truncated: boolean;
};

export type HistoryDelta = {
    before: number | null;
    after: number | null;
    delta: number | null;
    percent_change: number | null;
};

export type HistoryComparisonWarning = {
    code: string;
    message: string;
};

export type HistoryParameterChange = {
    field: string;
    before: unknown;
    after: unknown;
};

export type HistoryComponentKey =
    | 'demand_momentum'
    | 'competition_opportunity'
    | 'audience_reachability'
    | 'content_freshness_gap'
    | 'creator_viability';

export type HistoryVideoEntity = {
    provider_video_id: string;
    title: string;
    result_rank: number;
    view_count: number | null;
    views_per_day: number | null;
};

export type HistoryRetainedVideo = Omit<HistoryVideoEntity, 'views_per_day'> & {
    before_rank: number;
    after_rank: number;
    views_per_day: HistoryDelta;
};

export type HistoryChannelEntity = {
    provider_channel_id: string;
    title: string;
    subscriber_count: number | null;
    subscriber_count_hidden: boolean;
};

export type HistoryRetainedChannel = Omit<
    HistoryChannelEntity,
    'subscriber_count'
> & {
    subscriber_count: HistoryDelta;
};

export type HistoryChangeSet<T, R> = {
    before_count: number;
    after_count: number;
    new: T[];
    lost: T[];
    retained: R[];
    leading_before: T[];
    leading_after: T[];
};

export type HistoryComparison = {
    before: {
        public_id: string;
        query_text: string;
        market_key: MarketKey;
        kind: 'search' | 'discovery_validation';
        requested_result_count: number;
        collected_result_count: number;
        completed_at: string | null;
        formula_version: string | null;
    };
    after: {
        public_id: string;
        query_text: string;
        market_key: MarketKey;
        kind: 'search' | 'discovery_validation';
        requested_result_count: number;
        collected_result_count: number;
        completed_at: string | null;
        formula_version: string | null;
    };
    compatibility: {
        comparable: boolean;
        score_comparable: boolean;
        parameter_changes: HistoryParameterChange[];
        warnings: HistoryComparisonWarning[];
    };
    score_deltas: {
        overall_score: HistoryDelta;
        confidence_score: HistoryDelta;
        components: Record<HistoryComponentKey, HistoryDelta>;
    };
    metric_deltas: Record<string, HistoryDelta>;
    videos: HistoryChangeSet<HistoryVideoEntity, HistoryRetainedVideo>;
    channels: HistoryChangeSet<HistoryChannelEntity, HistoryRetainedChannel>;
    sample_overlap: {
        shared_videos: number;
        union_videos: number;
        share_percent: number | null;
    };
    stability: {
        retained_videos: number;
        unchanged_rank_count: number;
        unchanged_rank_percent: number | null;
    };
    new_breakout_channels: {
        value: number | null;
        reason: string;
    };
};

export type HistoryPair = {
    before: HistoryRun;
    after: HistoryRun;
};

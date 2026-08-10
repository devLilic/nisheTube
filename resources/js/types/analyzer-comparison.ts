export type AnalyzerComparisonAttemptOption = {
    public_id: string;
    observed_at: string | null;
    completed_at: string | null;
    cohort_video_count: number;
    has_topic_performance: boolean;
    has_thumbnail_performance: boolean;
};

export type AnalyzerComparisonOption = {
    channel_id: number;
    channel_title: string;
    provider_channel_id: string;
    attempt_count: number;
    attempts: [
        AnalyzerComparisonAttemptOption,
        ...AnalyzerComparisonAttemptOption[],
    ];
};

export type AnalyzerComparisonValue = {
    sample_count: number;
    view_sample_count: number;
    median_views: number | null;
    views_per_day_sample_count: number;
    median_views_per_day: number | null;
    breakout_count: number;
    breakout_sample_count: number;
    breakout_rate_percent: number | null;
};

export type AnalyzerComparisonRun = {
    public_id: string;
    channel_title: string;
    provider_channel_id: string;
    observed_at: string | null;
    completed_at: string | null;
    cache_policy: string;
    market: { key: string | null; source: string };
    cohort_video_count: number;
    requested_video_count: number;
    channel_model: {
        calculation_version: string | null;
        behavior_version: string | null;
        threshold_version: string | null;
    };
    semantic_model: null | {
        status: string;
        calculation_version: string;
        topic_version: string | null;
        title_pattern_version: string;
        minimum_sample_size: number;
        cohort_video_count: number;
        calculated_at: string;
    };
    thumbnail_model: null | {
        status: string;
        provider: string;
        algorithm_version: string;
        calculation_version: string;
        minimum_sample_size: number;
        cohort_video_count: number;
        calculated_at: string | null;
    };
};

export type CrossChannelComparison = {
    runs:
        | [AnalyzerComparisonRun, AnalyzerComparisonRun]
        | [AnalyzerComparisonRun, AnalyzerComparisonRun, AnalyzerComparisonRun];
    compatibility: {
        channel_metrics: boolean;
        topics: boolean;
        title_patterns: boolean;
        thumbnails: boolean;
        warnings: Array<{ code: string; message: string }>;
    };
    channel_metrics: Array<{
        key: string;
        label: string;
        unit: string;
        values: Array<{ value: number | null; sample_count: number }>;
    }>;
    topic_rows: Array<{
        key: string;
        label: string;
        values: Array<AnalyzerComparisonValue | null>;
    }>;
    title_pattern_rows: Array<{
        key: string;
        label: string;
        values: Array<AnalyzerComparisonValue | null>;
    }>;
    thumbnail_rows: Array<{
        key: string;
        label: string;
        values: Array<AnalyzerComparisonValue | null>;
    }>;
    disclaimer: string;
};

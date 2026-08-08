export type MarketKey = 'global_en' | 'ro_ro' | 'ru_ru';

export type ResearchRunStatus =
    | 'draft'
    | 'queued'
    | 'searching'
    | 'enriching'
    | 'scoring'
    | 'completed'
    | 'failed';

export type ResearchMarket = {
    key: MarketKey;
    name: string;
};

export type ResearchRunError = {
    code: string;
    title: string;
    message: string;
    guidance: string;
    action: 'settings' | 'new_search' | 'retry';
};

export type ResearchResultPreview = {
    provider_video_id: string;
    provider_channel_id: string;
    title: string;
    published_at: string;
    result_rank: number;
};

export type ResearchAnalysisSummary = {
    video_count: number;
    channel_count: number;
    coverage_percent: number;
    median_views: number | null;
    median_views_per_day: number | null;
    median_subscribers: number | null;
    median_reach_ratio: number | null;
    median_engagement_rate: number | null;
    hidden_subscriber_channels: number;
    missing_video_metric_count: number;
    earliest_collected_at: string | null;
    latest_collected_at: string | null;
};

export type ResearchVideoAnalysis = {
    provider_video_id: string;
    title: string;
    thumbnail_url: string | null;
    channel_id: string;
    channel_title: string;
    result_rank: number;
    view_count: number | null;
    like_count: number | null;
    comment_count: number | null;
    engagement_rate: number | null;
    age_days: number | null;
    views_per_day: number | null;
    reach_ratio: number | null;
    duration_seconds: number | null;
    category_id: string | null;
    is_short: boolean | null;
    published_at: string;
    collected_at: string;
};

export type ResearchChannelAnalysis = {
    provider_channel_id: string;
    title: string;
    thumbnail_url: string | null;
    custom_url: string | null;
    country: string | null;
    subscriber_count: number | null;
    subscriber_count_hidden: boolean;
    view_count: number | null;
    video_count: number | null;
    lifetime_uploads_per_month: number | null;
    sample_video_count: number;
    median_views: number | null;
    median_views_per_day: number | null;
    median_reach_ratio: number | null;
    collected_at: string | null;
};

export type ResearchAnalysis = {
    summary: ResearchAnalysisSummary;
    videos: ResearchVideoAnalysis[];
    channels: ResearchChannelAnalysis[];
};

export type OpportunityScoreWarning = {
    code: string;
    message: string;
};

export type OpportunityScoreComponent = {
    key:
        | 'demand_momentum'
        | 'competition_opportunity'
        | 'audience_reachability'
        | 'content_freshness_gap'
        | 'creator_viability';
    label: string;
    score: number;
    weight_percent: number;
    explanation: string;
};

export type OpportunityScore = {
    overall_score: number;
    overall_label: string;
    confidence_score: number;
    confidence_label: string;
    formula_version: string;
    sample_size: number;
    calculated_at: string;
    components: OpportunityScoreComponent[];
    warnings: OpportunityScoreWarning[];
};

export type ResearchRun = {
    public_id: string;
    query_text: string;
    market: ResearchMarket;
    status: ResearchRunStatus;
    attempt_number: number;
    requested_result_count: number;
    collected_result_count: number;
    enriched_result_count: number;
    progress_percent: number;
    collection_warnings: string[];
    parameters: {
        search_order: string;
        published_after: string | null;
        published_before: string | null;
        video_duration: string | null;
        video_category_id: string | null;
    };
    created_at: string | null;
    started_at: string | null;
    search_completed_at: string | null;
    completed_at: string | null;
    failed_at: string | null;
    is_active: boolean;
    can_retry: boolean;
    error: ResearchRunError | null;
    collection?: {
        pages_collected: number;
        sample_results: ResearchResultPreview[];
    };
    score?: OpportunityScore | null;
    analysis?: ResearchAnalysis;
};

export type ResearchMarketOption = {
    key: MarketKey;
    name: string;
    region_code: string | null;
    relevance_language: string;
};

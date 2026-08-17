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
    detected_topic_profile: DetectedTopicSummary | null;
};

export type DetectedTopicSummary = {
    niche: string | null;
    topics: string[];
    language: string;
    version: string;
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
    detected_topic_profile: DetectedTopicSummary | null;
};

export type ResearchAnalysis = {
    summary: ResearchAnalysisSummary;
    videos: ResearchVideoAnalysis[];
    channels: ResearchChannelAnalysis[];
};

export type ResearchProvenanceGroup = {
    key: 'api' | 'calculated';
    label: 'YouTube Data' | 'Calculated Metrics';
    description: string;
};

export type ResearchProvenanceSource = {
    public_id: string;
    provider: string;
    kind: 'search_enrichment';
    status: 'queued' | 'collecting' | 'completed' | 'failed';
    cache_policy: 'fresh_only' | 'allow_fresh_cache' | 'force_refresh';
    freshness_state: 'empty' | 'fresh' | 'cached' | 'mixed';
    freshness_window_seconds: number;
    historical_backfill: boolean;
    observed_from: string | null;
    observed_to: string | null;
    video_observation_count: number;
    channel_observation_count: number;
    fresh_observation_count: number;
    cached_observation_count: number;
    result_count: number;
    pinned_video_count: number;
    pinned_channel_count: number;
    quota_attempt_count: number;
    quota_estimated_cost: number;
    endpoints: Array<{
        endpoint: string;
        quota_bucket: string;
        request_count: number;
        estimated_cost: number;
    }>;
    warnings: string[];
    groups: ResearchProvenanceGroup[];
};

export type ResearchEvidenceItem = {
    provider_video_id: string;
    title: string;
    thumbnail_url: string | null;
    channel_id: string;
    channel_title: string;
    result_rank: number;
    relevance: {
        class: 'strictly_relevant' | 'related' | 'weakly_related' | 'off_topic';
        score: number;
        signals: {
            title_coverage?: number;
            exact_title_phrase?: boolean;
            semantic_matches?: number;
            category_matches?: number;
            topic_matches?: number;
            negative_matches?: string[];
            detected_languages?: string[];
            language_match?: boolean;
            requested_format?: string;
            format_match?: boolean | null;
        };
    } | null;
    view_count: number | null;
    views_per_day: number | null;
    engagement_rate: number | null;
    subscriber_count: number | null;
    subscriber_count_hidden: boolean;
    reach_ratio: number | null;
    published_at: string;
    collected_at: string | null;
    duration_seconds: number | null;
    format: 'shorts' | 'long_form' | 'unknown';
    breakout_class: string | null;
    metrics_complete: boolean;
};

export type ResearchEvidenceInspection = {
    items: ResearchEvidenceItem[];
    pagination: {
        page: number;
        page_size: number;
        total: number;
        last_page: number;
        from: number;
        to: number;
    };
    query: {
        sort:
            | 'relevance'
            | 'views_per_day'
            | 'engagement'
            | 'channel_size'
            | 'reach_ratio'
            | 'published_at'
            | 'breakout_class';
        direction: 'asc' | 'desc';
        filter: string;
    };
    limits: {
        page_size: number;
        channel_size_bands: Record<string, string>;
    };
    relevance: {
        state: 'provider_order_only' | 'versioned';
        label: string;
        version: string | null;
        description: string;
    };
    filters: Array<{
        key: string;
        label: string;
        enabled: boolean;
        reason: string | null;
    }>;
};

export type ResearchRobustSample = {
    count: number;
    metric_count: number;
    state: 'available' | 'insufficient';
    median_views_per_day: number | null;
    p25_views_per_day: number | null;
    p75_views_per_day: number | null;
    p90_views_per_day: number | null;
    trimmed_mean_views_per_day: number | null;
};

export type ResearchOutlierEvidence = {
    state: 'available' | 'insufficient';
    metric_count: number;
    top_video_share?: number | null;
    dependency: 'low' | 'medium' | 'high' | null;
    removals: Array<{
        removed_top_count: number;
        remaining_count: number;
        median_views_per_day: number | null;
        mean_views_per_day: number | null;
    }>;
};

export type ResearchEvidenceProfile = {
    state: 'pending' | 'not_calculated' | 'available';
    version: string | null;
    normalization_version?: string;
    description: string;
    full_sample_count: number;
    strict_sample_count: number;
    sample_evidence: {
        full: ResearchRobustSample;
        strict: ResearchRobustSample;
        class_counts: Record<string, number>;
    } | null;
    format_evidence: Record<
        'shorts' | 'long_form' | 'unknown',
        ResearchRobustSample
    > | null;
    outlier_evidence: {
        full: ResearchOutlierEvidence;
        strict: ResearchOutlierEvidence;
    } | null;
    stability: {
        state: 'available' | 'unavailable' | 'insufficient_metrics';
        label: 'high' | 'medium' | 'low' | null;
        reason: string | null;
        previous_overlap_count?: number;
        metric_pair_count?: number;
        result_overlap?: number | null;
        channel_overlap?: number | null;
        order_stability?: number | null;
        metric_variance?: number | null;
        median_variation?: number | null;
    };
    warnings: string[];
    calculated_at: string | null;
};

export type ResearchProvenance = {
    state: 'loading' | 'empty' | 'ready' | 'partial' | 'error';
    message: string;
    source: ResearchProvenanceSource | null;
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
    sample_views: {
        full_sample_count: number;
        strict_sample_count: number | null;
    };
    calculated_at: string;
    components: OpportunityScoreComponent[];
    warnings: OpportunityScoreWarning[];
};

export type ProfitabilityFit = {
    fit_score: number;
    confidence_score: number;
    formula_version: string;
    calculated_at: string;
    source_formula_version: string;
    explanations: Record<string, string>;
    warnings: OpportunityScoreWarning[];
};

export type ResearchDecisionCoverage = {
    key: string;
    label: string;
    available: number;
    total: number;
    percent: number | null;
    state: 'complete' | 'partial' | 'unavailable';
};

export type ResearchDecisionSummary = {
    lifecycle: {
        key:
            | 'active'
            | 'complete_data'
            | 'partial_data'
            | 'reduced_confidence'
            | 'failed_with_partial'
            | 'failed';
        label: string;
        description: string;
    };
    verdict: string;
    interpretation: string;
    active_progress: {
        stage: string;
        percent: number;
        collected_count: number;
        requested_count: number;
        warning_count: number;
        eta_label: string;
        eta_explanation: string;
    } | null;
    completeness: ResearchDecisionCoverage[];
    stability: {
        key: 'not_measured' | 'high' | 'medium' | 'low';
        label: string;
        description: string;
    };
    observation: {
        collected_at: string | null;
        freshness_key: 'fresh' | 'aging' | 'stale' | 'unavailable';
        freshness_label: string;
        age_hours: number | null;
    };
    sample_size: number;
    principal_evidence: Array<{
        label: string;
        value: string;
        explanation: string;
    }>;
    risks: string[];
    next_action: {
        kind: 'wait' | 'retry' | 'new_search' | 'review_evidence';
        label: string;
        description: string;
    };
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
        workflow_mode: string;
        preset_key: string;
        language: string;
        content_format: string;
        target_channel_size: string;
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
    profitability_fit?: ProfitabilityFit | null;
    analysis?: ResearchAnalysis;
    provenance?: ResearchProvenance;
    decision_summary?: ResearchDecisionSummary;
    evidence_inspection?: ResearchEvidenceInspection;
    evidence_profile?: ResearchEvidenceProfile;
};

export type ResearchMarketOption = {
    key: MarketKey;
    name: string;
    region_code: string | null;
    relevance_language: string;
};

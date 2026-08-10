export type AnalyzerRunStatus =
    | 'queued'
    | 'fetching_video'
    | 'fetching_channel'
    | 'loading_recent_videos'
    | 'calculating_metrics'
    | 'saving_analysis'
    | 'completed'
    | 'failed';

export type AnalyzerRunError = {
    code: string;
    title: string;
    message: string;
    guidance: string;
    action: 'settings' | 'retry' | 'new_analysis';
};

export type AnalyzerVideoProfile = {
    provider_video_id: string;
    title: string;
    thumbnail_url: string | null;
    youtube_url: string;
    published_at: string;
    duration_seconds: number | null;
    category: { id: string; name: string | null } | null;
    view_count: number | null;
    like_count: number | null;
    comment_count: number | null;
    observed_at: string;
    source_mode: 'fresh' | 'cached';
    first_seen_at: string | null;
};

export type AnalyzerChannelProfile = {
    provider_channel_id: string;
    title: string;
    custom_url: string | null;
    thumbnail_url: string | null;
    youtube_url: string;
    country: string | null;
    subscriber_count: number | null;
    subscriber_count_hidden: boolean;
    view_count: number | null;
    video_count: number | null;
    observed_at: string | null;
    source_mode: 'fresh' | 'cached' | null;
    first_seen_at: string | null;
};

export type AnalyzerVideoMetrics = {
    age_seconds: number;
    lifetime_views_per_day: number | null;
    views_to_subscribers_ratio: number | null;
    channel_median_ratio: number | null;
    channel_average_ratio: number | null;
    recent_rank: number | null;
    recent_percentile: number | null;
    recent_comparison_count: number;
    breakout_class: AnalyzerBreakoutClass | null;
    threshold_version: string | null;
    previous_video_snapshot_id: number | null;
    observed_elapsed_seconds: number | null;
    observed_view_delta: number | null;
    observed_like_delta: number | null;
    observed_comment_delta: number | null;
    observed_recent_views_per_day: number | null;
    observed_view_growth_percent: number | null;
    behavior_version: string | null;
    like_rate_percent: number | null;
    comment_rate_percent: number | null;
    public_engagement_rate_percent: number | null;
    calculation_version: string;
    calculated_at: string;
    warnings: string[];
};

export type AnalyzerChannelMetrics = {
    recent_valid_count: number;
    recent_requested_count: number;
    coverage_percent: number;
    median_views: number | null;
    average_views: number | null;
    minimum_views: number | null;
    maximum_views: number | null;
    median_likes: number | null;
    median_comments: number | null;
    median_duration_seconds: number | null;
    average_duration_seconds: number | null;
    minimum_duration_seconds: number | null;
    maximum_duration_seconds: number | null;
    median_age_days: number | null;
    average_upload_gap_days: number | null;
    median_upload_gap_days: number | null;
    longest_upload_gap_days: number | null;
    videos_per_week: number | null;
    videos_per_month: number | null;
    duration_distribution: Record<string, number>;
    category_distribution: Array<{
        id: string;
        name: string | null;
        count: number;
    }>;
    strong_count: number | null;
    strong_share_percent: number | null;
    breakout_count: number | null;
    breakout_share_percent: number | null;
    threshold_version: string | null;
    momentum_recent_count: number;
    momentum_previous_count: number;
    momentum_recent_median_views_per_day: number | null;
    momentum_previous_median_views_per_day: number | null;
    momentum_ratio: number | null;
    momentum_class: 'declining' | 'stable' | 'growing' | null;
    consistency_sample_count: number;
    consistency_score: number | null;
    consistency_class: 'consistent' | 'mixed' | 'volatile' | null;
    duration_performance_sample_count: number;
    duration_performance_correlation: number | null;
    duration_performance_class: string | null;
    duration_performance_buckets: Record<
        string,
        { count: number; median_views_per_day: number | null }
    >;
    previous_channel_snapshot_id: number | null;
    observed_elapsed_seconds: number | null;
    observed_view_delta: number | null;
    observed_subscriber_delta: number | null;
    observed_video_delta: number | null;
    observed_view_growth_percent: number | null;
    behavior_version: string | null;
    calculation_version: string;
    calculated_at: string;
    warnings: string[];
};

export type AnalyzerRecentVideo = {
    provider_video_id: string;
    title: string;
    youtube_url: string;
    thumbnail_url: string | null;
    published_at: string;
    published_within_recent_window: boolean;
    source_position: number;
    duration_seconds: number | null;
    category: { id: string; name: string | null } | null;
    view_count: number | null;
    like_count: number | null;
    comment_count: number | null;
    age_seconds: number | null;
    lifetime_views_per_day: number | null;
    observed_at: string;
    source_mode: 'fresh' | 'cached';
    channel_median_ratio: number | null;
    breakout_class: AnalyzerBreakoutClass | null;
    threshold_version: string | null;
    local_analysis: {
        public_id: string;
        url: string;
    } | null;
};

export type AnalyzerBreakoutClass = {
    value:
        'underperformer' | 'normal' | 'above_average' | 'strong' | 'breakout';
    label: string;
};

export type AnalyzerRun = {
    public_id: string;
    target_kind: 'video' | 'channel';
    target_provider_id: string;
    display_label: string;
    display_identity: {
        video_title: string | null;
        channel_title: string | null;
        thumbnail_url: string | null;
        provider_id: string;
        is_resolved: boolean;
    };
    status: AnalyzerRunStatus;
    status_label: string;
    attempt_number: number;
    progress_percent: number;
    cache_policy: 'fresh_only' | 'allow_fresh_cache' | 'force_refresh';
    origin: {
        kind: string;
        reference: string | null;
        return_url: string | null;
    };
    warnings: string[];
    is_active: boolean;
    can_refresh: boolean;
    error: AnalyzerRunError | null;
    created_at: string | null;
    started_at: string | null;
    calculated_at: string | null;
    completed_at: string | null;
    failed_at: string | null;
    recent_video_window: {
        days: 90;
        reference_at: string | null;
        starts_at: string | null;
    };
    video?: AnalyzerVideoProfile | null;
    channel?: AnalyzerChannelProfile | null;
    metrics?: AnalyzerVideoMetrics | null;
    channel_metrics?: AnalyzerChannelMetrics | null;
    recent_videos?: AnalyzerRecentVideo[];
    cohort?: {
        requested_limit: number;
        playlist_item_count: number;
        unavailable_count: number;
        collection_complete: boolean;
        uploads_playlist_id: string | null;
    };
    relative_context?: {
        threshold_version: string;
        minimum_baseline_count: number;
        strong_ratio: number;
        breakout_ratio_exclusive: number;
    };
    behavior_context?: {
        version: string | null;
        momentum_block_size: number;
        minimum_consistency_sample: number;
        minimum_correlation_sample: number;
        declining_below: number;
        growing_above: number;
    };
    growth_history?: {
        first_seen_at: string | null;
        retention_cutoff_at: string;
        points: Array<{
            attempt_public_id: string;
            observed_at: string;
            video_snapshot_id: number | null;
            view_count: number | null;
            like_count: number | null;
            comment_count: number | null;
            channel_view_count: number | null;
            channel_subscriber_count: number | null;
            channel_video_count: number | null;
            elapsed_seconds: number | null;
            view_delta: number | null;
            like_delta: number | null;
            comment_delta: number | null;
            observed_recent_views_per_day: number | null;
            view_growth_percent: number | null;
        }>;
    };
    topic_profile?: AnalyzerTopicProfile | null;
    topic_performance?: AnalyzerTopicPerformance | null;
    comments?: AnalyzerComments;
    transcript?: AnalyzerTranscript;
    thumbnail_analysis?: AnalyzerThumbnailAnalysis;
    curation?: {
        video: AnalyzerCuration | null;
        channel: AnalyzerCuration;
    };
    handoffs?: {
        watchlist: AnalyzerHandoff;
        topic_workspace: AnalyzerHandoff;
    };
};

export type AnalyzerTranscriptSegment = {
    position: number;
    start_ms: number | null;
    end_ms: number | null;
    text: string;
};

export type AnalyzerTranscript = {
    status: 'not_provided' | 'available' | 'partial';
    can_manage: boolean;
    document: null | {
        public_id: string;
        provider: 'user_provided';
        provider_version: string;
        input_format: 'plain_text' | 'timestamped_text' | 'srt' | 'vtt';
        language: 'en' | 'ro' | 'ru' | 'und';
        character_count: number;
        segment_count: number;
        warnings: string[];
        provided_at: string;
        retention_cutoff_at: string;
        revision_count: number;
        analysis: AnalyzerTranscriptStructure | null;
        segments: AnalyzerTranscriptSegment[];
    };
};

export type AnalyzerTranscriptStructureInsight = {
    kind:
        | 'summary'
        | 'topic'
        | 'entity'
        | 'hook'
        | 'section'
        | 'cta'
        | 'question'
        | 'script_structure';
    label: string;
    detail: string | null;
    confidence: number;
    position: number;
    start_offset: number;
    end_offset: number;
    start_ms: number | null;
    end_ms: number | null;
    evidence_text: string;
};

export type AnalyzerTranscriptStructure = {
    public_id: string;
    status: 'insufficient' | 'partial' | 'failed' | 'complete';
    provenance: 'inferred';
    provider: string;
    algorithm_version: string;
    language: 'en' | 'ro' | 'ru' | 'und';
    word_count: number;
    evidence_count: number;
    confidence_score: number | null;
    warnings: string[];
    calculated_at: string;
    insights: AnalyzerTranscriptStructureInsight[];
};

export type AnalyzerThumbnailItem = {
    provider_video_id: string;
    title: string;
    thumbnail_url: string | null;
    role: 'anchor' | 'channel_recent_upload';
    status: 'available' | 'unavailable';
    cache_status: 'fresh' | 'reused';
    width: number | null;
    height: number | null;
    aspect_ratio: number | null;
    average_brightness: number | null;
    average_saturation: number | null;
    contrast_score: number | null;
    edge_density: number | null;
    dominant_color: string | null;
    brightness_class: string | null;
    saturation_class: string | null;
    contrast_class: string | null;
    composition_class: string | null;
    cluster_key: string | null;
    confidence_score: number | null;
    error_code: string | null;
    analyzed_at: string | null;
};

export type AnalyzerThumbnailAggregate = {
    cluster_key: string;
    label: string;
    meets_minimum_sample: boolean;
    sample_count: number;
    view_sample_count: number;
    median_views: number | null;
    average_views: number | null;
    views_per_day_sample_count: number;
    median_views_per_day: number | null;
    average_views_per_day: number | null;
    breakout_sample_count: number;
    breakout_count: number;
    breakout_rate_percent: number | null;
    evidence_video_ids: string[];
};

export type AnalyzerThumbnailAnalysis = {
    status:
        | 'not_requested'
        | 'queued'
        | 'processing'
        | 'complete'
        | 'partial'
        | 'insufficient'
        | 'failed';
    is_active: boolean;
    can_analyze: boolean;
    profile: null | {
        public_id: string;
        status:
            | 'queued'
            | 'processing'
            | 'complete'
            | 'partial'
            | 'insufficient'
            | 'failed';
        provenance: 'inferred';
        provider: string;
        algorithm_version: string;
        calculation_version: string;
        attempt_number: number;
        minimum_sample_size: number;
        cohort_video_count: number;
        processed_image_count: number;
        available_image_count: number;
        reused_image_count: number;
        unavailable_image_count: number;
        confidence_score: number | null;
        warnings: string[];
        error_code: string | null;
        error_message: string | null;
        started_at: string | null;
        calculated_at: string | null;
        failed_at: string | null;
        items: AnalyzerThumbnailItem[];
        aggregates: AnalyzerThumbnailAggregate[];
    };
};

export type AnalyzerComments = {
    public_id?: string;
    status:
        | 'not_requested'
        | 'queued'
        | 'collecting'
        | 'completed'
        | 'empty'
        | 'partial'
        | 'comments_disabled'
        | 'unavailable'
        | 'quota_exhausted'
        | 'failed';
    is_active: boolean;
    can_collect: boolean;
    max_comments?: number;
    pages_collected?: number;
    comments_collected?: number;
    reported_total_results?: number | null;
    has_more?: boolean;
    reply_scope?: 'top_level_only';
    error_code?: string | null;
    error_message?: string | null;
    collected_at?: string | null;
    retention_cutoff_at: string | null;
    audience_signals?: AnalyzerAudienceSignals | null;
    pagination: {
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
        from: number | null;
        to: number | null;
    };
    items: Array<{
        id: number;
        text: string;
        like_count: number | null;
        reply_count: number;
        published_at: string | null;
        updated_at: string | null;
        is_saved: boolean;
        saved_idea_public_id: string | null;
    }>;
};

export type AnalyzerAudienceSignal = {
    kind:
        | 'repeated_question'
        | 'topic'
        | 'entity'
        | 'suggestion'
        | 'complaint'
        | 'confusion_point';
    label: string;
    can_exclude: boolean;
    confidence: number | null;
    comment_count: number;
    occurrence_count: number;
    evidence: Array<{
        comment_id: number;
        text: string;
        published_at: string | null;
    }>;
};

export type AnalyzerAudienceSignals = {
    public_id: string;
    status: 'complete' | 'partial' | 'insufficient' | 'unsafe' | 'failed';
    provenance: 'inferred';
    provider: string;
    algorithm_version: string;
    language: string;
    comment_count: number;
    usable_comment_count: number;
    confidence_score: number | null;
    warnings: string[];
    calculated_at: string;
    hidden_signal_count: number;
    excluded_words: Array<{
        public_id: string;
        word: string;
        excluded_at: string;
    }>;
    signals: AnalyzerAudienceSignal[];
};

export type AnalyzerSemanticLabel = {
    label: string;
    key: string;
    confidence: number | null;
    evidence_video_ids?: string[];
};

export type AnalyzerTopicProfile = {
    public_id: string;
    status: 'complete' | 'partial' | 'insufficient' | 'failed';
    provenance: 'inferred';
    provider: string;
    algorithm_version: string;
    language: string;
    niche: AnalyzerSemanticLabel | null;
    subniche: AnalyzerSemanticLabel | null;
    topics: AnalyzerSemanticLabel[];
    content_pillars: AnalyzerSemanticLabel[];
    concentration_score: number | null;
    confidence_score: number | null;
    evidence_video_count: number;
    warnings: string[];
    calculated_at: string;
};

export type AnalyzerTopicPerformanceAggregate = {
    group_type: 'topic' | 'title_pattern';
    label: string;
    key: string;
    is_unclassified: boolean;
    meets_minimum_sample: boolean;
    sample_count: number;
    view_sample_count: number;
    median_views: number | null;
    average_views: number | null;
    views_per_day_sample_count: number;
    median_views_per_day: number | null;
    average_views_per_day: number | null;
    breakout_sample_count: number;
    breakout_count: number;
    breakout_rate_percent: number | null;
    evidence_video_ids: string[];
};

export type AnalyzerTopicPerformance = {
    public_id: string;
    status: 'complete' | 'partial' | 'insufficient' | 'failed';
    provenance: 'inferred';
    calculation_version: string;
    topic_version: string | null;
    title_pattern_version: string;
    minimum_sample_size: number;
    cohort_video_count: number;
    warnings: string[];
    calculated_at: string;
    aggregates: AnalyzerTopicPerformanceAggregate[];
};

export type AnalyzerCuration = {
    research_status: 'unreviewed' | 'researching' | 'promising' | 'ruled_out';
    note: string | null;
};

export type AnalyzerHandoff = {
    available: boolean;
    target_kind: string;
    target_reference: string;
    watchlist_public_id?: string | null;
};

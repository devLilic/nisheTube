export type RetentionCounts = {
    research_runs: number;
    analyzer_runs: number;
    comment_collections: number;
    public_comments: number;
    transcript_documents: number;
    transcript_segments: number;
    thumbnail_analysis_profiles: number;
    thumbnail_analysis_items: number;
    thumbnail_performance_aggregates: number;
    video_snapshots: number;
    channel_snapshots: number;
    opportunity_scores: number;
    search_pages: number;
    search_results: number;
    video_memberships: number;
    expired_exports: number;
    preserved_favorites: number;
    preserved_shared_sources: number;
};

export type RetentionPreviewRun = {
    public_id: string;
    query_text: string;
    status: 'completed' | 'failed';
    terminal_at: string;
    favorite_impacted: boolean;
    shared_source_impacted: boolean;
    video_snapshots: number;
    channel_snapshots: number;
    opportunity_scores: number;
    artifacts: number;
};

export type RetentionAuditItem = {
    target_type: 'research_run' | 'analyzer_run' | 'export';
    target_reference: string;
    original_collection_at: string;
    outcome:
        | 'eligible'
        | 'preserved_favorite'
        | 'preserved_shared_source'
        | 'deleted'
        | 'skipped_missing'
        | 'skipped_ineligible';
    favorite_impacted: boolean;
    deleted_at: string | null;
};

export type RetentionAudit = {
    public_id: string;
    mode: 'scheduled' | 'manual_retention' | 'manual_selection';
    status: 'previewed' | 'queued' | 'processing' | 'completed' | 'failed';
    dry_run: boolean;
    cutoff_at: string;
    eligible_counts: Partial<RetentionCounts>;
    deleted_counts: Partial<RetentionCounts>;
    started_at: string | null;
    created_at: string | null;
    completed_at: string | null;
    failed_at: string | null;
    error_code: string | null;
    error_message: string | null;
    items: RetentionAuditItem[];
};

export type RetentionWorkspaceData = {
    preview: {
        cutoff_at: string;
        counts: RetentionCounts;
        runs: RetentionPreviewRun[];
    };
    has_active: boolean;
    history: RetentionAudit[];
    refreshed_at: string;
};

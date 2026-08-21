import type { MarketKey } from './research';

export type ExportFormat = 'csv' | 'xlsx';
export type ExportStatus = 'queued' | 'processing' | 'completed' | 'failed';

export type ExportBuilderRun = {
    public_id: string;
    query_text: string;
    market_key: MarketKey;
    completed_at: string | null;
    video_count: number;
    warning_count: number;
    score: number | null;
    confidence: number | null;
    videos: Array<{ id: string; title: string; rank: number }>;
};

export type ExportColumn = {
    key: string;
    label: string;
    required: boolean;
};

export type ExportBuilderData = {
    runs: ExportBuilderRun[];
    column_groups: Record<string, ExportColumn[]>;
    default_columns: string[];
    standard_columns: string[];
    max_runs: number;
    expiry_days: number;
    shortlist_run_ids: string[];
    topic_workspaces: Array<{
        public_id: string;
        name: string;
        run_ids: string[];
    }>;
};

export type ExportJob = {
    public_id: string;
    format: ExportFormat;
    status: ExportStatus;
    run_count: number;
    selection_type:
        | 'research_runs'
        | 'shortlist'
        | 'comparison'
        | 'topic_workspace'
        | 'semantic_performance';
    selection_label: string;
    column_count: number;
    size_bytes: number | null;
    created_at: string | null;
    started_at: string | null;
    completed_at: string | null;
    expires_at: string | null;
    expired: boolean;
    can_download: boolean;
    error_message: string | null;
};

export type ExportJobsData = {
    items: ExportJob[];
    has_active: boolean;
};

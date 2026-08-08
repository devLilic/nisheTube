import type { QuotaSummary } from './quota';
import type { MarketKey, ResearchRunStatus } from './research';

export type DashboardScore = {
    overall_score: number;
    confidence_score: number;
    formula_version: string;
    calculated_at: string | null;
};

export type DashboardRecentRun = {
    public_id: string;
    query_text: string;
    market_key: MarketKey;
    status: ResearchRunStatus;
    progress_percent: number;
    collected_result_count: number;
    enriched_result_count: number;
    has_partial_data: boolean;
    error_code: string | null;
    error_message: string | null;
    created_at: string | null;
    completed_at: string | null;
    failed_at: string | null;
    score: DashboardScore | null;
};

export type DashboardOpportunity = {
    public_id: string;
    query_text: string;
    market_key: MarketKey;
    overall_score: number;
    confidence_score: number;
    formula_version: string;
    sample_size: number;
    completed_at: string | null;
    calculated_at: string | null;
};

export type DashboardTrendPoint = {
    public_id: string;
    query_text: string;
    market_key: MarketKey;
    overall_score: number;
    confidence_score: number;
    formula_version: string;
    calculated_at: string | null;
};

export type DashboardData = {
    generated_at: string;
    counts: {
        research_runs_this_month: number;
        active_runs: number;
        failed_runs_this_month: number;
        saved_projects: number;
        saved_items: number | null;
    };
    availability: {
        saved_items: boolean;
        discovery_candidates: boolean;
    };
    best_opportunity: DashboardOpportunity | null;
    recent_runs: DashboardRecentRun[];
    top_opportunities: DashboardOpportunity[];
    opportunity_window_days: number;
    score_trend: DashboardTrendPoint[];
    quota: QuotaSummary;
    cleanup: {
        status: 'current' | 'due';
        cutoff_at: string;
        candidate_run_count: number;
        oldest_candidate_at: string | null;
    };
};

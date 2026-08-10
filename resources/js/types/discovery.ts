import type { MarketKey, ResearchRunStatus } from './research';

export type DiscoveryRunStatus =
    'draft' | 'queued' | 'analyzing' | 'completed' | 'failed';

export type NicheCandidateStatus = 'new' | 'saved' | 'dismissed' | 'validated';

export type DiscoverySampleRun = {
    public_id: string;
    query_text: string;
    market_key: MarketKey;
    video_count: number;
    completed_at: string | null;
};

export type DiscoverySeed = {
    query: string;
    source: 'user' | 'expanded';
    research_run: {
        public_id: string;
        query_text: string;
        completed_at: string | null;
    } | null;
};

export type NicheCandidate = {
    public_id: string;
    phrase: string;
    summary: string | null;
    status: NicheCandidateStatus;
    overall_score: number | null;
    confidence_score: number | null;
    formula_version: string | null;
    evidence: {
        observed_signal?: string;
        video_ids?: string[];
        seed_queries?: string[];
        member_phrases?: string[];
        source_video_count?: number;
        seed_count?: number;
        analyzer_run_ids?: string[];
        evidence_provenance?: ('research_snapshot' | 'analyzer_profile')[];
        opportunity_score_status?: 'requires_validation_search';
        inferred_topics?: string[];
        inferred_topic_provenance?: string | null;
    };
    validation_run: {
        public_id: string;
        status: ResearchRunStatus;
    } | null;
};

export type DiscoveryRun = {
    public_id: string;
    status: DiscoveryRunStatus;
    market: { key: MarketKey; name: string };
    parameters: {
        sample_per_seed: number;
        candidate_limit: number;
        formula_version: string;
    };
    seed_count: number;
    candidate_count: number;
    progress_percent: number;
    created_at: string | null;
    started_at: string | null;
    completed_at: string | null;
    failed_at: string | null;
    is_active: boolean;
    can_retry: boolean;
    error: { code: string; message: string } | null;
    partial_warnings: string[];
    seeds: DiscoverySeed[];
    candidates: NicheCandidate[];
};

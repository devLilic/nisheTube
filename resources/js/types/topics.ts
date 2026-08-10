export type TopicWorkspaceSummary = {
    public_id: string;
    name: string;
    description: string | null;
    market_key: string;
    language: string;
    archived_at: string | null;
    items_count: number;
    project: { public_id: string; name: string } | null;
    updated_at: string | null;
};

export type TopicEvidenceType =
    | 'video'
    | 'channel'
    | 'research_query'
    | 'research_run'
    | 'niche_candidate'
    | 'analyzer_run'
    | 'watchlist_item';

export type TopicEvidenceRole =
    | 'evidence'
    | 'example'
    | 'outlier'
    | 'competitor'
    | 'inspiration'
    | 'counterexample';

export type TopicEvidenceItem = {
    id: number;
    type: TopicEvidenceType;
    role: TopicEvidenceRole;
    label: string;
    note: string | null;
    url: string | null;
    market_key: string | null;
    cross_market_warning: string | null;
    detected_topic_profile: {
        niche: string;
        language: string;
        version: string;
        provenance: 'inferred';
    } | null;
};
